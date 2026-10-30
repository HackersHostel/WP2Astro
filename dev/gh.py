# Mock GitHub API on :9999 (token ghp_test). Git-compatible blob ids, real trees/commits/refs,
# non-forced ref updates. Writes the branch head to dev-out/ghrepo/ after every push.
# POST /__edit {"path","content"} simulates someone pushing an edit to the repo.
# Git LFS under /__lfs (point the wpta_github_lfs filter at http://127.0.0.1:9999/__lfs): batch API, basic
# transfer and verify, with Basic auth; objects are checked against their sha256 and kept in S['lfs'].
import json, hashlib, base64, os, shutil
from http.server import BaseHTTPRequestHandler, HTTPServer

S = {'repo': None, 'blobs': {}, 'trees': {}, 'commits': {}, 'ref': None, 'log': [], 'lfs': {}}
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
    json.dump({'commits': len(S['log']), 'parents': c['parents'], 'message': c['message'], 'files': len(head_files()), 'tree_entries_sent': S.get('last_tree_entries'), 'lfs_objects': len(S['lfs']), 'log': S['log']}, open(OUT + '.json', 'w'), indent=1)

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
    def lfs_auth(self):
        a = self.headers.get('Authorization', '')
        if not a.startswith('Basic ') or not base64.b64decode(a[6:]).decode().endswith(':ghp_test'): self.j(401, {'message': 'Bad credentials'}); return False
        return True
    def do_PUT(self):
        if self.path.startswith('/__lfs/upload/'):
            oid = self.path.rsplit('/', 1)[1]; data = self.rfile.read(int(self.headers.get('Content-Length', 0)))
            if hashlib.sha256(data).hexdigest() != oid: return self.j(422, {'message': 'oid mismatch'})
            S['lfs'][oid] = data; return self.j(200, {})
        self.j(404, {'message': '?' + self.path})
    def do_POST(self):
        if self.path.startswith('/__lfs/'):
            if not self.lfs_auth(): return
            b = self.body()
            if self.path == '/__lfs/objects/batch':
                out = []
                for o in b['objects']:
                    if o['oid'] in S['lfs']: out.append({'oid': o['oid'], 'size': o['size']}); continue
                    out.append({'oid': o['oid'], 'size': o['size'], 'actions': {
                        'upload': {'href': 'http://127.0.0.1:9999/__lfs/upload/' + o['oid'], 'header': {'X-Mock': 'upload'}},
                        'verify': {'href': 'http://127.0.0.1:9999/__lfs/verify', 'header': {}}}})
                return self.j(200, {'transfer': 'basic', 'objects': out})
            if self.path == '/__lfs/verify': return self.j(200 if b['oid'] in S['lfs'] else 404, {})
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
            files = {e['path']: e['sha'] for e in S['trees'][b['base_tree']]} if b.get('base_tree') else {}
            S['last_tree_entries'] = len(b['tree'])
            for e in b['tree']:
                if e['sha'] is None: assert e['path'] in files, 'delete of a missing path ' + e['path']; del files[e['path']]; continue
                assert e['sha'] in S['blobs'], 'missing blob ' + e['path']; files[e['path']] = e['sha']
            return self.j(201, {'sha': put_tree([{'path': k, 'sha': v} for k, v in sorted(files.items())])})
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
