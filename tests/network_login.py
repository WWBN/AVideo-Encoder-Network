"""Exercise the actual login handler with an isolated PHP root and mock video site.
No production sessions, configuration, credentials, or database are used.
"""
from pathlib import Path
import http.server
import json
import shutil
import subprocess
import tempfile
import threading
import urllib.parse

repo = Path(__file__).resolve().parents[1]
mode = {'body': {}, 'code': 200}
requests = []
class VideoSite(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        requests.append(urllib.parse.parse_qs(self.rfile.read(int(self.headers['Content-Length'])).decode()))
        self.send_response(mode['code']); self.end_headers()
        self.wfile.write(mode['body'].encode() if isinstance(mode['body'], str) else json.dumps(mode['body']).encode())
    def log_message(self, *args): pass
server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), VideoSite)
threading.Thread(target=server.serve_forever, daemon=True).start()
checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1
    print('PASS:', label)
try:
    with tempfile.TemporaryDirectory(prefix='network-login-test-') as directory:
        root = Path(directory); (root/'objects').mkdir()
        for name in ['Login.php', 'login.json.php']:
            shutil.copy(repo/'objects'/name, root/'objects'/name)
        (root/'configuration.php').write_text('<?php $_SESSION = [];', encoding='utf-8')
        (root/'objects/functions.php').write_text('<?php', encoding='utf-8')
        (root/'objects/Streamer.php').write_text('''<?php class Streamer {
            static function createIfNotExists(...$args){return 1;}
            function __construct($id){} function setPass($pass){} function save(){return 1;}
        }''', encoding='utf-8')
        def run(body, code=200, post=None, options=None):
            mode.update(body=body, code=code)
            fields = post if post is not None else {'user': "o'brien", 'pass': 'test-password',
                'siteURL': 'http://127.0.0.1:'+str(server.server_port)+'/', 'encodedPass': 'false'}
            script = "$_POST=json_decode(stream_get_contents(STDIN),true);require 'objects/login.json.php';"
            result = subprocess.run(['php', *(options or []), '-r', script], input=json.dumps(fields),
                text=True, capture_output=True, cwd=root, timeout=20)
            assert result.returncode == 0 and not result.stderr, result.stderr
            return json.loads(result.stdout)
        success = {'isLogged': True, 'canUpload': True, 'isAdmin': False, 'user': "o'brien", 'pass': 'encoded-token'}
        check(run(success)['canUpload'], 'Valid sign-in succeeds through the actual endpoint')
        check(requests[-1]['user'] == ["o'brien"] and requests[-1]['pass'] == ['test-password'], 'POST preserves special characters in credentials')
        check(run(success, options=['-d', 'allow_url_fopen=0'])['isLogged'], 'Sign-in still sends POST when allow_url_fopen is disabled')
        check(run(success, options=['-d', 'disable_functions=curl_init'])['isLogged'], 'Stream transport works when cURL is unavailable')
        for body in ['<html>Error</html>', 'null', '[]', 'true', {'isLogged': True, 'canUpload': False}]:
            check(not run(body)['canUpload'], 'Invalid or denied login response fails without a PHP fatal error: '+str(body))
        check(not run(success, code=503)['isLogged'], 'HTTP error cannot authorize a session')
        check('error' in run(success, post={'user': ['invalid'], 'pass': 'test', 'siteURL': 'https://example.com/'}), 'Array form fields return a JSON validation error')
        count = len(requests)
        check(not run(success, post={'user': 'test', 'pass': 'test', 'siteURL': 'file:///tmp/'})['isLogged'] and len(requests) == count, 'Non-HTTP site URLs are rejected before network access')
finally:
    server.shutdown(); server.server_close()
print(str(checks)+' login checks passed.')
