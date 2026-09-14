"""Integration tests using disposable databases and an isolated PHP document root.
Run: python tests/installer_integration.py --mysql D:/xampp/mysql/bin/mysql.exe
Requires a local MySQL account with CREATE/DROP privileges (defaults to root).
Never reads or writes the application's configuration.php or database.
"""
import argparse
import hashlib
import http.cookiejar
import http.server
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--mysql', default='mysql')
parser.add_argument('--php', default='php')
parser.add_argument('--user', default='root')
parser.add_argument('--port', default=3306, type=int)
parser.add_argument('--browser', action='store_true', help='Also exercise the real form with Playwright and Chrome')
args = parser.parse_args()
repo = Path(__file__).resolve().parents[1]
prefix = 'aen_test_' + uuid.uuid4().hex[:10]
databases = []

def sql(statement):
    return subprocess.check_output([args.mysql, '--host=127.0.0.1', '--port=' + str(args.port), '--user=' + args.user,
        '--batch', '--skip-column-names', '--execute=' + statement], text=True, encoding='utf-8').strip()

def database(suffix):
    name = prefix + '_' + suffix
    databases.append(name)
    return name

def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]

expected_hash = subprocess.check_output([args.php, '-r', "echo md5(hash('whirlpool',sha1(stream_get_contents(STDIN))));"], input="test'password\\safe", text=True).strip()

class Streamer(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        body = urllib.parse.parse_qs(self.rfile.read(int(self.headers['Content-Length'])).decode())
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({'isAdmin': body.get('pass') == [expected_hash] and body.get('encodedPass') == ['true']}).encode())
    def log_message(self, *args): pass

mock = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Streamer)
threading.Thread(target=mock.serve_forever, daemon=True).start()
passed = []
def check(condition, label):
    assert condition, label
    passed.append(label)
    print('PASS:', label)

try:
    with tempfile.TemporaryDirectory(prefix='avideo-installer-test-') as directory:
        root = Path(directory)
        shutil.copytree(repo / 'install', root / 'install')
        shutil.copytree(repo / 'objects', root / 'objects')
        port = free_port()
        process = subprocess.Popen([args.php, '-S', '127.0.0.1:' + str(port), '-t', str(root)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            base = 'http://127.0.0.1:' + str(port)
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            for _ in range(50):
                try:
                    page = opener.open(base + '/install/index.php').read().decode()
                    break
                except OSError: time.sleep(.1)
            token = re.search(r'name="token" value="([^"]+)"', page).group(1)
            check(not (root / 'configuration.php').exists(), 'Opening setup does not create configuration.php')
            data = {'token': token, 'databaseHost': '127.0.0.1', 'databasePort': str(args.port), 'databaseUser': args.user,
                'databasePass': os.environ.get('MYSQL_PWD', ''), 'databaseName': database('success'), 'webSiteRootURL': base + '/',
                'siteURL': 'http://127.0.0.1:' + str(mock.server_port) + '/', 'inputUser': "admin'quoted", 'inputPassword': "test'password\\safe",
                'allowedEncoders': 'https://encoder.example.org/\n\nhttps://encoder.example.org/\nhttps://encoder2.example.org/'}
            def post(**changes):
                values = dict(data, **changes)
                request = urllib.request.Request(base + '/install/checkConfiguration.php', data=urllib.parse.urlencode(values).encode())
                try: response = opener.open(request)
                except urllib.error.HTTPError as e: response = e
                body = response.read().decode('utf-8')
                parsed = json.loads(body)
                assert data['inputPassword'] not in body
                return parsed
            r = post(token='wrong')
            check(r['error'] and 'session' in r['msg'], 'Rejects missing/invalid CSRF token')
            r = post(databaseName='bad`; DROP DATABASE mysql;')
            check(r['error'] and r['stage'] == 'validation', 'Rejects database identifier injection')
            r = post(action='test', databasePort=str(free_port()))
            check(r['error'] and r['help'].get('command') and r['stage'] == 'connection', 'Connection failure includes a repair command')
            r = post(action='test', databaseUser='aen_missing_user', databasePass='must-not-leak')
            check(r['error'] and 'must-not-leak' not in json.dumps(r) and r['help'].get('sql'), 'Authentication failure includes safe SQL guidance without password')
            r = post(action='test')
            check(not r['error'] and not (root / 'configuration.php').exists() and not sql("SHOW DATABASES LIKE '" + data['databaseName'] + "'"), 'Connection test creates no database or configuration')
            r = post(inputPassword='incorrect')
            check(r['error'] and r['stage'] == 'streamer', 'Administrator validation occurs on the server')
            r = post(allowedEncoders='javascript:alert(1)')
            check(r['error'] and r['stage'] == 'validation', 'Rejects non-HTTP encoder URLs')
            occupied = database('occupied')
            sql(f'CREATE DATABASE `{occupied}`; CREATE TABLE `{occupied}`.other_data (id INT); INSERT INTO `{occupied}`.other_data VALUES (42);')
            r = post(databaseName=occupied)
            check(r['error'] and sql(f'SELECT id FROM `{occupied}`.other_data') == '42' and not (root / 'configuration.php').exists(), 'Existing database contents are preserved')
            broken = database('broken')
            original_schema = (root / 'install/database.sql').read_text()
            (root / 'install/database.sql').write_text(original_schema + '\nINVALID INSTALLER SQL;\n')
            r = post(databaseName=broken)
            check(r['error'] and r['stage'] == 'tables' and not (root / 'configuration.php').exists(), 'Schema error stops installation and does not publish configuration')
            (root / 'install/database.sql').write_text(original_schema)
            sql(f"CREATE TRIGGER `{broken}`.fail_encoder BEFORE INSERT ON `{broken}`.encoders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test rollback';")
            r = post(databaseName=broken)
            check(r['error'] and r['stage'] == 'records' and sql(f'SELECT COUNT(*) FROM `{broken}`.streamers') == '0', 'Record insertion failure rolls back initial registrations')
            sql(f'DROP TRIGGER `{broken}`.fail_encoder')
            sql(f'ALTER TABLE `{broken}`.streamers AUTO_INCREMENT=12')
            # Force final rename to fail; staged configuration must remain recoverable.
            (root / 'configuration.php').mkdir()
            r = post(databaseName=broken)
            check(r['error'] and r['stage'] == 'configuration' and r['help'].get('command') and len(list(root.glob('.configuration-*.php'))) == 1, 'Publication failure preserves ready configuration and shows recovery command')
            check(sql(f'SELECT streamers_id FROM `{broken}`.encoders LIMIT 1') == '12', 'Retried installation links encoders to a non-default streamer ID')
            (root / 'configuration.php').rmdir()
            for staged in root.glob('.configuration-*.php'): staged.unlink()
            if args.browser:
                from playwright.sync_api import sync_playwright
                with sync_playwright() as playwright:
                    browser = playwright.chromium.launch(channel='chrome', headless=True)
                    page = browser.new_page()
                    page.goto(base + '/install/index.php')
                    for key, value in data.items():
                        if key != 'token': page.locator('[name="' + key + '"]').fill(value)
                    page.get_by_role('button', name='Install Encoder Network').click()
                    page.get_by_role('heading', name='Installation complete.').wait_for()
                    check(page.locator('#configurationForm').count() == 0 and page.get_by_role('link', name='Open application').is_visible(), 'Browser form completes installation and removes the form to prevent duplicate submission')
                    browser.close()
            else:
                r = post()
                check(not r['error'] and r['installed'] and len(r['steps']) == 7, 'Full installation creates database, tables, records and configuration')
            check(sql(f'SELECT COUNT(*) FROM `{data["databaseName"]}`.encoders') == '2', 'Blank encoder lines are ignored and duplicate URLs are deduplicated')
            check(sql(f'SELECT COUNT(*) FROM `{data["databaseName"]}`.encoders e JOIN `{data["databaseName"]}`.streamers s ON s.id=e.streamers_id') == '2', 'Encoders reference the actual inserted streamer ID')
            config = (root / 'configuration.php').read_text()
            check('__DIR__' in config and "$mysqlPort = '" + str(args.port) + "'" in config, 'Generated configuration has a portable root and the selected database port')
            subprocess.check_call([args.php, '-l', str(root / 'configuration.php')], stdout=subprocess.DEVNULL)
            check(True, 'Generated PHP is valid with quoted and backslash-containing credentials')
            runtime = subprocess.check_output([args.php, '-r', "require 'configuration.php'; echo $global['mysqli']->character_set_name();"], cwd=root, text=True)
            check(runtime == 'utf8mb4', 'Application bootstrap connects using generated configuration and UTF-8 charset')
            check(sql('SELECT pass FROM `' + data['databaseName'] + '`.streamers LIMIT 1') == expected_hash, 'Stored credentials match the Streamer encoded-password protocol')
            persistence = subprocess.check_output([args.php, '-r', "require 'configuration.php'; require 'objects/functions.php'; require 'objects/Streamer.php'; $u=json_decode(stream_get_contents(STDIN),true); $id=Streamer::createIfNotExists($u, 'secret', 'https://test.invalid/'); $s=new Streamer($id); $s->setPass($u); $s->save(); $again=Streamer::createIfNotExists($u,'secret','https://test.invalid/'); $saved=new Streamer($again); echo json_encode([$id===$again,$saved->getUser(),$saved->getPass()]);"], input=json.dumps("o'brien\\test"), cwd=root, text=True)
            check(json.loads(persistence) == [True, "o'brien\\test", "o'brien\\test"], 'Streamer lookup, insertion, and update preserve quotes and backslashes')
            original = (root / 'configuration.php').read_bytes()
            r = post()
            check(r['error'] and (root / 'configuration.php').read_bytes() == original, 'Repeated install cannot overwrite configuration')
            (root / 'configuration.php').write_text('<?php this is deliberately invalid PHP')
            page = opener.open(base + '/install/index.php').read().decode()
            for secret in [str(root), 'name="token"', 'Ubuntu setup help', 'configurationForm', 'php.ini']:
                assert secret not in page, secret
            check('Installation complete.' in page and 'Fatal error' not in page, 'Existing broken configuration is not executed by installer')
            print('\n' + str(len(passed)) + ' checks passed.')
        finally:
            process.terminate(); process.wait(timeout=10)
finally:
    mock.shutdown()
    for name in databases:
        assert re.fullmatch(re.escape(prefix) + r'_[a-z]+', name)
        sql('DROP DATABASE IF EXISTS `' + name + '`')
