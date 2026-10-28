// Renders the WordPress.org plugin directory icons and banners into .wordpress-org/.
//   node dev/wporg/assets.js
// Sources: assets/logo.svg (icons) and dev/wporg/banner.svg (banners). Uses the pre-installed Playwright Chromium;
// the banner text is set in Inter, so install it first for identical output.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const out = path.join(root, '.wordpress-org');
const svg = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const jobs = [
  ['icon-128x128.png', 'assets/logo.svg', 128, 128],
  ['icon-256x256.png', 'assets/logo.svg', 256, 256],
  ['banner-772x250.png', 'dev/wporg/banner.svg', 772, 250],
  ['banner-1544x500.png', 'dev/wporg/banner.svg', 1544, 500],
];

(async () => {
  fs.mkdirSync(out, { recursive: true });
  fs.copyFileSync(path.join(root, 'assets/logo.svg'), path.join(out, 'icon.svg'));
  const browser = await chromium.launch();
  for (const [name, src, width, height] of jobs) {
    const page = await browser.newPage({ viewport: { width, height } });
    await page.setContent(`<!doctype html><style>html,body{margin:0;background:transparent}svg{display:block}</style>${svg(src)}`);
    await page.evaluate(([w, h]) => {
      const el = document.querySelector('svg');
      el.setAttribute('width', w);
      el.setAttribute('height', h);
    }, [width, height]);
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(out, name), omitBackground: true });
    await page.close();
    console.log(`.wordpress-org/${name}`);
  }
  await browser.close();
})();
