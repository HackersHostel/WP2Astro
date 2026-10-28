# Mock GitHub API on :9999 (token ghp_test). Git-compatible blob ids, real trees/commits/refs,
# non-forced ref updates. Writes the branch head to dev-out/ghrepo/ after every push.
# POST /__edit {"path","content"} simulates someone pushing an edit to the repo.
import json, hashlib, base64, os, shutil
from http.server import BaseHTTPRequestHandler, HTTPServer

S = {'repo': None, 'blobs': {}, 'trees': {}, 'commits': {}, 'ref': None, 'log': []}
OUT = './dev-out/ghrepo'

def blob_sha(data): return hashlib.sha1(b'blob %d\0' % len(data) + data).hexdigest()
def put_blob(data):
    sha = blob_sha(data); S['blobs'][sha] = data; return sha
def put_tree(entries):
    sha = hashlib.sha1(json.dumps(entries, sort_keys=True).encode()).hexdigest(); S['trees'][sha] = entries; return sha
def put_commit(tree, parents, message):
    sha = hashlib.sha1(json.dumps([tree, parents, message, len(S['commits'])]).encode()).hexdigest()
    S['commits'][sha] = {'tree': tree, 'parents': parents, 'message': message}; return sha
def head_files():
    return {e['path']: e['sha'] for e in S['trees'][S['commits'][S['ref']]['tree']]} if S['ref'] else {}
def checkout():
    shutil.rmtree(OUT, ignore_errors=True)
    for path, sha in head_files().items():
        f = os.path.join(OUT, path); os.makedirs(os.path.dirname(f), exist_ok=True); open(f, 'wb').write(S['blobs'][sha])
    c = S['commits'][S['ref']]
    json.dump({'commits': len(S['log']), 'parents': c['parents'], 'message': c['message'], 'files': len(head_files()), 'log': S['log']}, open(OUT + '.json', 'w'), indent=1)

class H(BaseHTTPRequestHandler):
    def j(self, code, data):
        b = json.dumps(data).encode(); self.send_response(code); self.send_header('Content-Type', 'application/json'); self.end_headers(); self.wfile.write(b)
    def body(self):
        n = int(self.headers.get('Content-Length', 0)); return json.loads(self.rfile.read(n)) if n else {}
    def auth(self):
        if self.headers.get('Authorization') != 'Bearer ghp_test': self.j(401, {'message': 'Bad credentials'}); return False
        return True
    def do_GET(self):
        if not self.auth(): return
        p = self.path
        if p == '/user': return self.j(200, {'login': 'labib'})
        if p.startswith('/repos/labib/') and '/git/' not in p:
            return self.j(200, S['repo']) if S['repo'] else self.j(404, {'message': 'Not Found'})
        if p.endswith('/git/ref/heads/main'): return self.j(200, {'object': {'sha': S['ref']}})
        if '/git/commits/' in p:
            sha = p.rsplit('/', 1)[1]; c = S['commits'][sha]
            return self.j(200, {'sha': sha, 'tree': {'sha': c['tree']}, 'parents': [{'sha': x} for x in c['parents']]})
        if '/git/trees/' in p:
            sha = p.rsplit('/', 1)[1].split('?')[0]
            return self.j(200, {'sha': sha, 'tree': [dict(e, type='blob', mode='100644') for e in S['trees'][sha]], 'truncated': False})
        self.j(404, {'message': '?' + p})
    def do_POST(self):
        b = self.body(); p = self.path
        if p == '/__edit':
            files = head_files(); files[b['path']] = put_blob(b['content'].encode())
            S['ref'] = put_commit(put_tree([{'path': k, 'sha': v} for k, v in sorted(files.items())]), [S['ref']], 'Edit ' + b['path'])
            S['log'].append('edit ' + b['path']); checkout(); return self.j(200, {'sha': S['ref']})
        if not self.auth(): return
        if p == '/user/repos':
            S['repo'] = {'name': b['name'], 'full_name': 'labib/' + b['name'], 'default_branch': 'main', 'html_url': 'https://github.com/labib/' + b['name'], 'private': b['private']}
            S['ref'] = put_commit(put_tree([{'path': 'README.md', 'sha': put_blob(('# ' + b['name'] + '\n').encode())}]), [], 'Initial commit')
            return self.j(201, S['repo'])
        if p.endswith('/git/blobs'): return self.j(201, {'sha': put_blob(base64.b64decode(b['content']))})
        if p.endswith('/git/trees'):
            assert 'base_tree' not in b
            for e in b['tree']: assert e['sha'] in S['blobs'], 'missing blob ' + e['path']
            return self.j(201, {'sha': put_tree([{'path': e['path'], 'sha': e['sha']} for e in b['tree']])})
        if p.endswith('/git/commits'): return self.j(201, {'sha': put_commit(b['tree'], b.get('parents', []), b['message'])})
        self.j(404, {'message': '?' + p})
    def do_PATCH(self):
        if not self.auth(): return
        b = self.body(); c = S['commits'][b['sha']]
        if not b.get('force') and S['ref'] not in c['parents']:
            return self.j(422, {'message': 'Update is not a fast forward'})
        S['ref'] = b['sha']; S['log'].append(c['message'].split('\n')[0]); checkout()
        self.j(200, {'object': {'sha': b['sha']}})
    def log_message(self, *a): pass

HTTPServer(('127.0.0.1', 9999), H).serve_forever()
