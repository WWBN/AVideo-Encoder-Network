"""Exercise the dashboard with an isolated PHP server and simulated encoder responses.
No production configuration, sessions, credentials, or databases are used.
Requires PHP, Python 3, Playwright, and Chrome. Run: python tests/network_dashboard.py
"""
from pathlib import Path
import http.server
import json
import shutil
import socket
import subprocess
import tempfile
import threading
import time
import urllib.request
from playwright.sync_api import sync_playwright

repo = Path(__file__).resolve().parents[1]
checks = []
mode = {'east': 'online', 'west': 'online'}
def check(condition, label):
    assert condition, label
    checks.append(label)
    print('PASS:', label, flush=True)
def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0)); return s.getsockname()[1]
class EncoderServer(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        side = 'east' if self.path.startswith('/east/') else 'west'
        payload = self.rfile.read(int(self.headers.get('Content-Length', 0))).decode()
        assert 'encodedPass=true' in payload and 'pass=fixture-hash' in payload
        if mode[side] == 'restricted':
            self.send_response(403); self.end_headers(); self.wfile.write(b'{"error":true}'); return
        self.send_response(200); self.send_header('Content-Type', 'application/json'); self.end_headers()
        if mode[side] == 'malformed': self.wfile.write(b'<html>Not a status response</html>'); return
        self.wfile.write(json.dumps({'queue_size': 4 if side == 'east' else 0, 'concurrent': 2 if side == 'east' else 4,
            'encoding': [{}] if side == 'east' else [], 'downloading': [], 'transferring': [],
            'file_upload_max_size': '2 GB', 'version': '7.0', 'memory': {'memTotalBytes': 8589934592, 'memFreeBytes': 4294967296, 'memUsedBytes': 4294967296},
            'queue_list': [{'private': 'must-not-leak'}], 'pass': 'must-not-leak'}).encode())
    def do_GET(self):
        self.send_response(200); self.send_header('Content-Type', 'text/html'); self.end_headers(); self.wfile.write(b'<h1>Test encoder workspace</h1>')
    def log_message(self, *args): pass
mock = http.server.ThreadingHTTPServer(('127.0.0.1',0),EncoderServer)
threading.Thread(target=mock.serve_forever,daemon=True).start()
output = Path(tempfile.gettempdir())/'avideo-network-qa';output.mkdir(exist_ok=True)
try:
    with tempfile.TemporaryDirectory(prefix='aen-dashboard-test-') as directory:
        root=Path(directory);shutil.copytree(repo/'view',root/'view');shutil.copytree(repo/'objects',root/'objects')
        (root/'sessions').mkdir();port=free_port();base='http://127.0.0.1:'+str(port)+'/'
        (root/'configuration.php').write_text("<?php\n$global['webSiteRootURL'] = '"+base+"'; $global['systemRootPath'] = __DIR__ . '/'; session_start();",encoding='utf-8')
        (root/'objects/Login.php').write_text("<?php require_once dirname(__DIR__) . '/configuration.php'; class Login { static function canUpload(){return !empty($_SESSION['testLogin']);} static function isAdmin(){return false;} static function isLogged(){return self::canUpload();} static function getStreamerURL(){return 'https://videos.example.com/';} static function logoff(){unset($_SESSION['testLogin']);} }",encoding='utf-8')
        (root/'objects/Streamer.php').write_text("<?php class Streamer { static function getFirstURL(){return 'https://videos.example.com/';} function getUser(){return 'fixture';} function getPass(){return 'outdated-stored-hash';} function getSiteURL(){return self::getFirstURL();} }",encoding='utf-8')
        rows=[{'id':i,'name':name,'siteURL':'http://127.0.0.1:'+str(mock.server_port)+'/'+side+'/','description':'Test fixture'} for i,name,side in [(1,'Encoder East','east'),(2,'Encoder West','west')]]
        row_json=json.dumps(rows).replace("'","\\'")
        (root/'objects/Encoder.php').write_text("<?php require_once __DIR__ . '/Streamer.php'; class Encoder {private $id; function __construct($id){$this->id=$id;} static function getAll(){return json_decode('"+row_json+"',true);} function getId(){return in_array($this->id,[1,2])?$this->id:null;} function getSiteURL(){return self::getAll()[$this->id-1]['siteURL'];} function getStreamer(){return new Streamer();}}",encoding='utf-8')
        # Seed only the isolated test session directory.
        subprocess.check_call(['php','-d','session.save_path='+str(root/'sessions'),'-r',"session_id('dashboard-test'); require 'configuration.php'; $_SESSION['testLogin']=true; $_SESSION['login']=(object)['user'=>'fixture','pass'=>'fixture-hash'];"],cwd=root)
        server=subprocess.Popen(['php','-d','session.save_path='+str(root/'sessions'),'-S','127.0.0.1:'+str(port),'-t',str(root)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        try:
            for _ in range(40):
                try: urllib.request.urlopen(base+'view/index.php');break
                except OSError:time.sleep(.1)
            with sync_playwright() as p:
                browser=p.chromium.launch(channel='chrome',headless=True)
                context=browser.new_context(viewport={'width':1536,'height':1050})
                context.add_cookies([{'name':'PHPSESSID','value':'dashboard-test','domain':'127.0.0.1','path':'/'}])
                page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
                page.goto(base+'view/index.php')
                page.wait_for_function("document.querySelector('#refreshStatus').textContent.startsWith('Last checked')")
                check(page.locator('#onlineCount').inner_text()=='2' and page.locator('#queueCount').inner_text()=='4' and page.locator('#capacityCount').inner_text()=='6','Network summaries use confirmed encoder metrics')
                check(page.locator('#recommendedName').inner_text()=='Encoder West','Recommendation uses queue-to-capacity ratio')
                check(page.locator('#selectedName').text_content()=='Encoder East' and page.locator('#memoryPercent').text_content()=='50% used','First encoder opens immediately while recommendation remains available')
                check(page.locator('iframe').count()==1 and page.locator('#workspace').is_visible(),'First encoder and queue open automatically without a click')
                page.frame_locator('#workspace-frame-1').get_by_role('heading',name='Test encoder workspace').wait_for()
                check(page.frame_locator('#workspace-frame-1').get_by_role('heading').is_visible(),'Embedded encoder content finishes loading')
                page.wait_for_timeout(15500)
                check(page.locator('#workspaceWarning').is_hidden(),'Loaded encoder does not receive a false slow-loading warning')
                check(page.title()=='Encoder Network' and 'AVideo' not in page.locator('body').inner_text() and page.locator('.brand, .brand-mark').count()==0,'Dashboard has no product branding')
                check(page.locator('#workspaceExternal').evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(252, 199, 13)','Primary actions use the installer palette')
                check(page.locator('#responseChart circle').first.evaluate('(e)=>getComputedStyle(e).fill')=='rgb(0, 108, 155)','Chart colors resolve correctly after the palette change')
                response=context.request.get(base+'view/networkStatus.json.php?id=1')
                check('must-not-leak' not in response.text() and 'queue_list' not in response.text(),'Status uses current session credentials and returns operational metrics only')
                check(context.request.get(base+'view/networkStatus.json.php?id=999').status==404,'Unknown encoder IDs are rejected')
                page.screenshot(path=str(output/'desktop-fixture.png'),full_page=True)
                page.get_by_label('Search encoders').fill('east')
                check(page.locator('#encoderRows tr').count()==1,'Encoder search filters the table')
                page.get_by_label('Search encoders').fill('not-found')
                check(page.locator('#emptyFleet').is_visible(),'Empty search results show guidance')
                page.get_by_label('Search encoders').fill('')
                original_frame=page.locator('#workspace-frame-1').get_attribute('src')
                page.get_by_role('button',name='View queue on Encoder West',exact=True).click()
                check(page.locator('#workspace-frame-2').is_visible() and page.locator('#workspace-frame-2').get_attribute('src').endswith('#encoding'),'Queue action opens the requested encoder in one click')
                check(page.locator('#workspace-frame-1').get_attribute('src')==original_frame and page.locator('#workspace-frame-1').is_hidden(),'Switching encoders retains the previous workspace')
                page.get_by_role('tab',name='Encoder East',exact=True).click()
                check(page.locator('iframe').count()==2 and page.locator('#workspace-frame-1').is_visible(),'Workspace tabs switch back without creating or reloading a frame')
                check(page.locator('#workspaceExternal').get_attribute('href').find('noNavbar')==-1,'Direct launch avoids iframe-only parameters')
                page.get_by_role('button',name='Close this encoder',exact=True).click()
                check(page.locator('iframe').count()==1 and page.locator('#workspace-frame-2').is_visible(),'Close removes only the active encoder and preserves the other')
                page.get_by_role('button',name='Open Encoder East',exact=True).click()
                check(page.locator('#workspace-frame-1').is_visible(),'Table action opens the encoder directly in one click')
                mode['east']='restricted';mode['west']='malformed'
                page.get_by_role('button',name='Refresh status',exact=True).click()
                page.wait_for_function("document.querySelector('#onlineCount').textContent==='0'")
                check(page.locator('#queueCount').text_content()==chr(8212) and page.locator('#useRecommended').is_disabled(),'Failed checks clear stale metrics and remove the recommendation')
                page.get_by_label('Filter encoder status').select_option('attention')
                check(page.locator('#encoderRows tr').count()==2,'Needs attention includes denied and invalid status responses')
                page.get_by_role('button',name='Open Encoder East',exact=True).click()
                check('denied status access' in page.locator('#selectedNotice').text_content(),'Encoder access failures include actionable guidance')
                page.locator('#advancedDetails').evaluate('(element) => element.open = true')
                page.screenshot(path=str(output/'error-fixture.png'),full_page=True)
                page.set_viewport_size({'width':390,'height':844})
                page.screenshot(path=str(output/'mobile-fixture.png'),full_page=True)
                check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),'Mobile layout has no page overflow')
                check(not errors,'No JavaScript runtime errors')
                guest=browser.new_context();guest_page=guest.new_page();guest_page.goto(base+'view/index.php')
                check(guest_page.locator('#loginForm').is_visible() and guest.request.get(base+'view/networkStatus.json.php?id=1').status==403,'Signed-out users see sign-in and cannot request status metrics')
                guest_page.screenshot(path=str(output/'login-fixture.png'),full_page=True)
                check(guest_page.title()=='Encoder Network' and 'AVideo' not in guest_page.locator('body').inner_text(),'Sign-in page is white label')
                guest_page.get_by_label('Username').fill('fixture')
                guest_page.get_by_label('Password',exact=True).fill('fixture')
                guest_page.route('**/login',lambda route:route.fulfill(status=503,json={'streamer':True,'isLogged':True,'canUpload':True}))
                guest_page.get_by_role('button',name='Sign in',exact=True).click()
                guest_page.locator('#loginError').wait_for()
                check('not confirmed' in guest_page.locator('#loginError').inner_text() and guest_page.locator('button[type=submit]').is_enabled(),'HTTP login errors stay on the form and allow retry')
                guest_page.unroute('**/login')
                guest_page.route('**/login',lambda route:route.fulfill(body='<html>server error</html>',content_type='text/html'))
                guest_page.get_by_role('button',name='Sign in',exact=False).click()
                guest_page.wait_for_function("document.querySelector('#loginError').textContent.includes('invalid response')")
                check(guest_page.locator('button[type=submit]').is_enabled(),'Malformed login responses show useful feedback and allow retry')
                browser.close()
        finally:server.terminate();server.wait(timeout=10)
finally:mock.shutdown()
print(str(len(checks))+' dashboard checks passed. Screenshots: '+str(output))
