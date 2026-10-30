// Visual check of a conversion (issue #23): screenshots the same pages on WordPress and on the Astro build, and
// prints how many pixels differ. The diff runs in the browser on a canvas, so Playwright is the only dependency.
// Screenshots and diff images go to dev-out/compare/; with GITHUB_STEP_SUMMARY set, a table goes to the job summary.
//   node dev/compare.mjs [--wp=http://localhost:8899] [--astro=http://localhost:4567] [--pages=/,/about/] [--max=100]
// --max: fail when any page differs by more than this percentage of pixels (default 100: report only).
import { chromium } from 'playwright';
import { appendFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const arg = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')));
const WP = arg.wp || 'http://localhost:8899';
const ASTRO = arg.astro || 'http://localhost:4567';
const pages = (arg.pages || '/,/2009/10/the-lighthouse-keeper-who-never-left/,/about/').split(',');
const max = Number(arg.max ?? 100);
const out = join(process.cwd(), 'dev-out', 'compare');
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const shot = async (url) => {
  await page.goto(url, { waitUntil: 'networkidle' });
  return (await page.screenshot()).toString('base64');
};

const rows = [];
for (const path of pages) {
  const name = path.replace(/^\/|\/$/g, '').replace(/[^\w-]+/g, '_') || 'home';
  const a = await shot(WP + path);
  const b = await shot(ASTRO + path);
  // Compare in the page: draw both screenshots on canvases and count pixels whose color moved more than 32/255.
  const r = await page.evaluate(async ([a, b]) => {
    const load = (src) => new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.src = 'data:image/png;base64,' + src; });
    const [ia, ib] = await Promise.all([load(a), load(b)]);
    const w = Math.max(ia.width, ib.width), h = Math.max(ia.height, ib.height);
    const px = (img) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h); };
    const da = px(ia), db = px(ib);
    const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); const diff = x.createImageData(w, h);
    let changed = 0, total = 0;
    for (let i = 0; i < da.data.length; i += 4) {
      const d = Math.max(Math.abs(da.data[i] - db.data[i]), Math.abs(da.data[i + 1] - db.data[i + 1]), Math.abs(da.data[i + 2] - db.data[i + 2]));
      total += d;
      const g = 255 - (255 - (da.data[i] + da.data[i + 1] + da.data[i + 2]) / 3) * 0.25;
      if (d > 32) { changed++; diff.data.set([230, 40, 40, 255], i); } else { diff.data.set([g, g, g, 255], i); }
    }
    x.putImageData(diff, 0, 0);
    const n = da.data.length / 4;
    return { changed: (100 * changed) / n, similarity: 100 - (100 * total) / (n * 255), diff: c.toDataURL('image/png').split(',')[1] };
  }, [a, b]);
  writeFileSync(join(out, `${name}-wordpress.png`), Buffer.from(a, 'base64'));
  writeFileSync(join(out, `${name}-astro.png`), Buffer.from(b, 'base64'));
  writeFileSync(join(out, `${name}-diff.png`), Buffer.from(r.diff, 'base64'));
  rows.push({ path, changed: r.changed, similarity: r.similarity });
  console.log(`${path.padEnd(52)} ${r.changed.toFixed(1).padStart(5)}% of pixels differ · ${r.similarity.toFixed(1)}% similar`);
}
await browser.close();

if (process.env.GITHUB_STEP_SUMMARY) {
  const md = ['### WordPress vs Astro', '', '| Page | Pixels that differ | Similarity |', '|---|---|---|',
    ...rows.map((r) => `| \`${r.path}\` | ${r.changed.toFixed(1)}% | ${r.similarity.toFixed(1)}% |`),
    '', 'Screenshots and diff images are in the `compare` artifact.', ''].join('\n');
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, md);
}
const worst = Math.max(...rows.map((r) => r.changed));
if (worst > max) {
  console.error(`A page differs by ${worst.toFixed(1)}% of its pixels (limit ${max}%).`);
  process.exit(1);
}
