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
class H(BaseHTTPRequestHandler):
    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        sysmsg, user = body['messages'][0]['content'], body['messages'][1]['content']
        open('./dev-out/llm.log','a').write(json.dumps({'model':body['model'],'auth':self.headers.get('Authorization'),'sys':sysmsg[:40],'len':len(user)})+'\n')
        if 'Astro components' in sysmsg:
            out = LAYOUT if body['model']!='badmodel' else "<?php get_header(); ?><div><slot /></div>"
            open('./dev-out/layout_prompt.txt','w').write(user)
        else:
            doc = user.split('DOCUMENT:\n',1)[1]
            doc = re.sub(r'\\?\[button url="([^"]+)"[^\]]*\\?\](.*?)\\?\[/button\\?\]', r'[\2](\1)', doc)
            doc = re.sub(r'\\?\[/?(vc_\w+|contact-form-7)[^\]]*\\?\]', '', doc)
            out = '<think>reasoning</think>' + doc
        resp = json.dumps({'choices':[{'message':{'role':'assistant','content':out}}]}).encode()
        self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(resp)
    def log_message(self,*a): pass
HTTPServer(('127.0.0.1', 11434), H).serve_forever()
