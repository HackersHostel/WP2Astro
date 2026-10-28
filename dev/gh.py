import json, hashlib, base64, os
from http.server import BaseHTTPRequestHandler, HTTPServer
S={'repo':None,'blobs':{},'trees':{},'commits':{},'ref':None}
OUT='./dev-out/ghrepo'
class H(BaseHTTPRequestHandler):
    def j(self,code,data):
        b=json.dumps(data).encode(); self.send_response(code); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(b)
    def body(self):
        n=int(self.headers.get('Content-Length',0)); return json.loads(self.rfile.read(n)) if n else {}
    def do_GET(self):
        assert self.headers['Authorization']=='Bearer ghp_test'
        p=self.path
        if p=='/user': return self.j(200,{'login':'labib'})
        if p.startswith('/repos/labib/') and '/git/' not in p:
            return self.j(200,S['repo']) if S['repo'] else self.j(404,{'message':'Not Found'})
        if p.endswith('/git/ref/heads/main'): return self.j(200,{'object':{'sha':S['ref']}})
        self.j(404,{'message':'?'+p})
    def do_POST(self):
        b=self.body(); p=self.path
        if p=='/user/repos':
            S['repo']={'name':b['name'],'full_name':'labib/'+b['name'],'default_branch':'main','html_url':'https://github.com/labib/'+b['name'],'private':b['private']}
            S['ref']='init'; return self.j(201,S['repo'])
        if p.endswith('/git/blobs'):
            data=base64.b64decode(b['content']); sha=hashlib.sha1(data).hexdigest(); S['blobs'][sha]=data; return self.j(201,{'sha':sha})
        if p.endswith('/git/trees'):
            sha=hashlib.sha1(json.dumps(b).encode()).hexdigest(); S['trees'][sha]=b['tree']; return self.j(201,{'sha':sha})
        if p.endswith('/git/commits'):
            sha=hashlib.sha1(json.dumps(b).encode()).hexdigest(); S['commits'][sha]=b; return self.j(201,{'sha':sha})
        self.j(404,{'message':'?'+p})
    def do_PATCH(self):
        b=self.body()
        S['ref']=b['sha']; c=S['commits'][b['sha']]
        for e in S['trees'][c['tree']]:
            f=os.path.join(OUT,e['path']); os.makedirs(os.path.dirname(f),exist_ok=True); open(f,'wb').write(S['blobs'][e['sha']])
        json.dump({'parents':c.get('parents'),'message':c['message'],'files':len(S['trees'][c['tree']])},open(OUT+'.json','w'))
        self.j(200,{'object':{'sha':b['sha']}})
    def log_message(self,*a): pass
HTTPServer(('127.0.0.1', 9999), H).serve_forever()
