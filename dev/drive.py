# Drives the real wp-admin UI endpoints: saves settings through the form, checks the model endpoints,
# runs a conversion through the AJAX steps and downloads the zip to dev-out/out.zip.
#   python3 dev/drive.py [provider] [model] [--noai] [--nogh] [--norun]
# provider: openrouter (default) | chatgpt | none. The test site's mu-plugin dev/test-llm.php points them at the
# mock on :11434. --norun only saves settings and checks Load models / Test.
# Never point this at a real site: it overwrites the plugin settings.
import urllib.request, urllib.parse, http.cookiejar, re, json, sys
B = 'http://localhost:8899'
args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = [a for a in sys.argv[1:] if a.startswith('--')]
provider = args[0] if args else 'openrouter'
model = args[1] if len(args) > 1 else {'openrouter': 'anthropic/claude-haiku-5.5', 'chatgpt': 'gpt-test'}.get(provider, '')

cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def req(path, data=None):
    d = urllib.parse.urlencode(data).encode() if data is not None else None
    return op.open(B + path, d, timeout=900).read().decode()
op.open(B + '/wp-login.php').read()
req('/wp-login.php', {'log': 'admin', 'pwd': 'admin', 'wp-submit': 'Log In', 'testcookie': '1'})
page = req('/wp-admin/admin.php?page=wp2astro')
assert 'Convert to Astro' in page or 'Sync now' in page, page[:500]

form_nonce = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
s = {'action': 'wpta_save', '_wpnonce': form_nonce, '_wp_http_referer': '/wp-admin/admin.php?page=wp2astro',
     'wpta[provider]': provider, 'wpta[models][' + provider + ']': model,
     'wpta[keys][openrouter]': 'sk-or-test', 'wpta[ai_layout]': '1', 'wpta[ai_cleanup]': '1',
     'wpta[repo_name]': 'ghost-town-gazette-astro', 'wpta[repo_private]': '1',
     'wpta[seo]': '1', 'wpta[feeds]': '1', 'wpta[search]': '1', 'wpta[cpts]': '1', 'wpta[agent_kit]': '1'}
if '--nogh' not in flags: s['wpta[github_token]'] = 'ghp_test'
if '--noai' in flags: s['wpta[provider]'] = 'none'
req('/wp-admin/admin-post.php', s)

page = req('/wp-admin/admin.php?page=wp2astro')
nonce = re.search(r'var WPTA = \{"ajax":"[^"]+","nonce":"([^"]+)"', page).group(1)
print('secrets masked in page:', 'ghp_test' not in page and 'sk-or-test' not in page)
ajax = lambda action, data={}: json.loads(req('/wp-admin/admin-ajax.php', {'action': action, '_ajax_nonce': nonce, **data}))

if s['wpta[provider]'] != 'none':
    print('models:', json.dumps(ajax('wpta_models', {'provider': provider})))
    print('test:', json.dumps(ajax('wpta_test', {'provider': provider})))
if '--norun' in flags: sys.exit(0)

r = ajax('wpta_start', {'force': 1})
job = r['data']['job']; print('\n'.join(r['data']['log']))
while True:
    r = ajax('wpta_step', {'job': job})
    if not r['success']: print(r); sys.exit(1)
    d = r['data']; print(f"[{d['phase']} {d['progress']}%]", '\n'.join(d['log']))
    if d['done']: break
print(json.dumps(d['result']))
if d['result'].get('zip'):
    z = op.open(d['result']['zip']).read(); open('./dev-out/out.zip', 'wb').write(z); print('zip bytes', len(z))
print('runs:', len(ajax('wpta_runs')['data']['runs']))
