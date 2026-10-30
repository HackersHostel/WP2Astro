// Checks the build of dev/fixture.php: every WordPress URL has a file in dist/, and `npm start` (server.mjs)
// answers old links with the right status. Run from anywhere: node dev/check-dist.mjs <fixture dir>
import { spawn } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

const dir = resolve(process.argv[2] || '.');
const dist = join(dir, 'dist');
let failed = 0;
const check = (name, ok, detail = '') => {
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${ok || !detail ? '' : ` (${detail})`}`);
  if (!ok) failed++;
};
const isFile = (rel) => existsSync(join(dist, rel)) && statSync(join(dist, rel)).isFile();

check('.html permalink is a file, not a folder', isFile('2024/01/legacy.html'));
check('non-ASCII slug', isFile('café/index.html'));
check('nested page', isFile('parent/child/index.html'));
check('custom post type entry', isFile('projects/alpha/index.html'));
check('custom post type archive', isFile('projects/index.html'));
check('blog page 2', isFile('page/2/index.html'));
check('blog page 3', isFile('page/3/index.html'));
check('category archive page 2', isFile('category/news/page/2/index.html'));
check('tag archive', isFile('tag/night/index.html'));
check('author archive', isFile('author/jane/index.html'));
check('author archive page 2', isFile('author/jane/page/2/index.html'));
check('year archive', isFile('2024/index.html'));
check('month archive', isFile('2024/01/index.html'));
check('month archive in the site time zone (Jan 31 20:00 UTC is Feb 1 at +06:00)', isFile('2024/02/index.html'));
check('previous year archive', isFile('2023/12/index.html'));
check('search index', existsSync(join(dist, 'pagefind')));

const legacy = isFile('2024/01/legacy.html') ? readFileSync(join(dist, '2024/01/legacy.html'), 'utf8') : '';
check('.html page canonical has no trailing slash', legacy.includes('href="https://example.test/2024/01/legacy.html"'));
const sitemap = isFile('sitemap.xml') ? readFileSync(join(dist, 'sitemap.xml'), 'utf8') : '';
check('sitemap lists the .html permalink', sitemap.includes('<loc>https://example.test/2024/01/legacy.html</loc>'));
check('sitemap lists archives', ['/author/jane/', '/category/news/', '/2024/02/'].every((p) => sitemap.includes(`<loc>https://example.test${p}</loc>`)));
const feb = isFile('2024/02/index.html') ? readFileSync(join(dist, '2024/02/index.html'), 'utf8') : '';
check('the Feb 1 post is listed in February', feb.includes('Just after midnight'));
const toml = readFileSync(join(dir, 'netlify.toml'), 'utf8');
check('netlify.toml redirects ?p=', toml.includes('query = { p = "1" }'));

// server.mjs, as `npm start` runs it.
const port = 4600 + Math.floor(Math.random() * 300);
const server = spawn(process.execPath, ['server.mjs'], { cwd: dir, env: { ...process.env, PORT: String(port), HOST: '127.0.0.1' } });
await new Promise((r) => server.stdout.once('data', r));
const get = async (path) => {
  const res = await fetch(`http://127.0.0.1:${port}${encodeURI(path)}`, { redirect: 'manual' });
  return { status: res.status, location: res.headers.get('location') || '' };
};
let r = await get('/2024/01/legacy.html');
check('npm start: .html permalink', r.status === 200, `${r.status}`);
r = await get('/2024/01/legacy.html/');
check('npm start: .html permalink with a slash redirects to the file', r.status === 301 && r.location === '/2024/01/legacy.html', `${r.status} ${r.location}`);
r = await get('/?p=1');
check('npm start: ?p= redirect', r.status === 301 && r.location === '/2024/01/night-report-one/', `${r.status} ${r.location}`);
r = await get('/café/');
check('npm start: non-ASCII page', r.status === 200, `${r.status}`);
r = await get('/2024/01/old-slug/');
check('npm start: changed slug', r.status === 301, `${r.status}`);
server.kill();

console.log(failed ? `\n${failed} check(s) failed.` : '\nAll dist checks passed.');
process.exit(failed ? 1 : 0);
