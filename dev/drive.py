import urllib.request, urllib.parse, http.cookiejar, re, json, sys
B='http://localhost:8899'
cj=http.cookiejar.CookieJar(); op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def req(path,data=None):
    d=urllib.parse.urlencode(data).encode() if data is not None else None
    return op.open(B+path,d,timeout=900).read().decode()
op.open(B+'/wp-login.php').read()
req('/wp-login.php',{'log':'admin','pwd':'admin','wp-submit':'Log In','testcookie':'1'})
page=req('/wp-admin/tools.php?page=wp-to-astro')
assert 'Convert to Astro' in page, page[:500]
# save settings through the real form
f=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
settings={'option_page':'wpta','action':'update','_wpnonce':f,'_wp_http_referer':'/wp-admin/tools.php?page=wp-to-astro',
 'wpta_settings[provider]':'ollama','wpta_settings[base_url]':'http://127.0.0.1:11434/v1','wpta_settings[model]':sys.argv[1] if len(sys.argv)>1 else 'gemma3',
 'wpta_settings[api_key]':'','wpta_settings[ai_layout]':'1','wpta_settings[ai_cleanup]':'1','wpta_settings[github_token]':'ghp_test','wpta_settings[repo_name]':'ghost-town-gazette-astro','wpta_settings[repo_private]':'1'}
if len(sys.argv)>2 and sys.argv[2]=='noai': settings['wpta_settings[provider]']='none'
req('/wp-admin/options.php',settings)
page=req('/wp-admin/tools.php?page=wp-to-astro')
nonce=re.search(r'var WPTA = \{"ajax":"[^"]+","nonce":"([^"]+)"',page).group(1)
print('masked token shown:', '••••' in page and 'ghp_test' not in page)
r=json.loads(req('/wp-admin/admin-ajax.php',{'action':'wpta_start','_ajax_nonce':nonce}))
job=r['data']['job']; print('\n'.join(r['data']['log']))
while True:
    r=json.loads(req('/wp-admin/admin-ajax.php',{'action':'wpta_step','_ajax_nonce':nonce,'job':job}))
    d=r['data']; print(f"[{d['phase']} {d['progress']}%]", '\n'.join(d['log']))
    if d['done']: break
print(json.dumps(d['result']))
z=op.open(d['result']['zip']).read(); open('./dev-out/out.zip','wb').write(z); print('zip bytes',len(z))
