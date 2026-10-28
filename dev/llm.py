import json, re, sys
from http.server import BaseHTTPRequestHandler, HTTPServer
LAYOUT = '''Sure! Here is your layout:

```astro
---
import site from '../site.json';
const { title, description = site.description } = Astro.props;
const pageTitle = title ? `${title} – ${site.title}` : site.title;
---
<html lang={site.language}>
<head>
<meta charset="utf-8" />
<title>{pageTitle}</title>
<meta name="description" content={description} />
{site.stylesheets.map((href) => <link rel="stylesheet" href={href} />)}
</head>
<body class="home blog">
<div id="wrapper" class="hfeed">
  <div id="header"><div id="masthead"><div id="branding" role="banner">
    <div id="site-title"><span><a href="/" rel="home">{site.title}</a></span></div>
    <div id="site-description">{site.description}</div>
  </div>
  <img src="/theme/images/headers/path.jpg" width="940" height="198" alt="" />
  <div id="access" role="navigation"><div class="menu-header"><ul id="menu-main" class="menu">
    {site.menu.map((item) => <li class="menu-item"><a href={item.url}>{item.label}</a></li>)}
  </ul></div></div></div></div>
  <div id="main"><div id="container"><div id="content" role="main"><slot /></div></div></div>
  <div id="footer" role="contentinfo"><div id="colophon"><div id="site-info"><a href="/" rel="home">{site.title}</a></div></div></div>
</div>
</body>
</html>
```
Hope this helps!'''
def reply(sysmsg, user, model):
    open('./dev-out/llm.log','a').write(json.dumps({'model':model,'sys':sysmsg[:40],'len':len(user)})+'\n')
    if 'Astro components' in sysmsg:
        open('./dev-out/layout_prompt.txt','w').write(user)
        return LAYOUT if model!='badmodel' else "<?php get_header(); ?><div><slot /></div>"
    if 'connectivity' in sysmsg:
        return 'OK'
    doc = user.split('DOCUMENT:\n',1)[1]
    doc = re.sub(r'\\?\[button url="([^"]+)"[^\]]*\\?\](.*?)\\?\[/button\\?\]', r'[\2](\1)', doc)
    doc = re.sub(r'\\?\[/?(vc_\w+|contact-form-7)[^\]]*\\?\]', '', doc)
    return '<think>reasoning</think>' + doc

class H(BaseHTTPRequestHandler):
    def send(self, code, body, ctype='application/json'):
        b = body.encode() if isinstance(body, str) else json.dumps(body).encode()
        self.send_response(code); self.send_header('Content-Type', ctype); self.end_headers(); self.wfile.write(b)
    def do_GET(self):
        if self.path.endswith('/models'):
            if 'chatgpt' in self.path: return self.send(200, {'models': [{'slug': 'gpt-test', 'display_name': 'GPT Test', 'visibility': 'list'}, {'slug': 'hidden', 'visibility': 'hide'}]})
            return self.send(200, {'data': [{'id': 'gemma3'}, {'id': 'free/model', 'pricing': {'prompt': '0', 'completion': '0'}}]})
        self.send(404, {'error': {'message': 'not found'}})
    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        if self.path.endswith('/chat/completions'):
            out = reply(body['messages'][0]['content'], body['messages'][1]['content'], body['model'])
            return self.send(200, {'choices':[{'message':{'role':'assistant','content':out}}]})
        if self.path.endswith('/messages'):  # Anthropic
            assert self.headers.get('x-api-key') and self.headers.get('anthropic-version')
            out = reply(body['system'], body['messages'][0]['content'], body['model'])
            return self.send(200, {'content': [{'type': 'text', 'text': out}]})
        if self.path.endswith('/responses'):  # Sign in with ChatGPT: SSE, store=false, stream=true
            assert body['store'] is False and body['stream'] is True and 'temperature' not in body
            out = reply(body['instructions'], body['input'][0]['content'], body['model'])
            ev = [{'type': 'response.created'}] + [{'type': 'response.output_text.delta', 'delta': out[i:i+50]} for i in range(0, len(out), 50)] + [{'type': 'response.completed', 'response': {}}]
            return self.send(200, ''.join('event: %s\ndata: %s\n\n' % (e['type'], json.dumps(e)) for e in ev), 'text/event-stream')
        self.send(404, {'error': {'message': 'not found'}})
    def log_message(self,*a): pass
HTTPServer(('127.0.0.1', 11434), H).serve_forever()
