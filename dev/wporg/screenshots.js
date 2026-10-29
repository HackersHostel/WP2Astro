// Captures the WordPress.org screenshots, in the order of `== Screenshots ==` in readme.txt.
//   node dev/wporg/screenshots.js [--out=.wordpress-org]
// Needs the local test site from dev/README.md on :8899 (admin/admin, dev/test-gh.php and dev/test-llm.php
// mu-plugins), the mocks (python3 dev/llm.py, python3 dev/gh.py) and one finished conversion served with
// `PORT=4567 npm start` for the before/after. Like drive.py, it changes the plugin settings: never run it on a real site.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const WP = 'http://localhost:8899';
const ASTRO = 'http://localhost:4567';
const POST = '/2009/10/the-lighthouse-keeper-who-never-left/';
const arg = process.argv.find((a) => a.startsWith('--out='));
const out = path.resolve(arg ? arg.slice(6) : path.join(__dirname, '..', '..', '.wordpress-org'));
const shot = (n) => path.join(out, `screenshot-${n}.png`);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Answer one admin-ajax action from the test instead of the server.
const fakeAjax = (page, action, reply) => page.route(`${WP}/wp-admin/admin-ajax.php`, (route) => {
  const body = route.request().postData() || '';
  if (!body.includes(`action=${action}`)) return route.fallback();
  return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: reply }) });
});

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const dashboard = async (hash = '') => {
    await page.goto(`${WP}/wp-admin/admin.php?page=wp2astro${hash}`, { waitUntil: 'networkidle' });
  };

  await page.goto(`${WP}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

  // Settings for the shots: OpenRouter with a key (the mock answers), GitHub through the mock.
  await dashboard();
  await page.check('input[name="wpta[provider]"][value="openrouter"]');
  await page.fill('#wpta-key-openrouter', 'sk-or-test');
  await page.fill('#wpta-model-openrouter', 'google/gemma-4-31b-it');
  await Promise.all([page.waitForNavigation(), page.click('#ai .wpta-save button')]);

  // 3. Connect GitHub with a one-time code (GitHub's device endpoints are faked; nothing is sent to github.com).
  await page.evaluate(() => fetch(WPTA.ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'wpta_gh_disconnect', _ajax_nonce: WPTA.nonce }) }));
  await dashboard('#github');
  await fakeAjax(page, 'wpta_gh_start', { user_code: 'WDJB-MJHT', verification_uri: 'https://github.com/login/device', interval: 600, expires_in: 900 });
  await page.click('.wpta-gh-start');
  await page.waitForSelector('.wpta-gh-device:not([hidden])');
  await page.locator('#github').screenshot({ path: shot(3) });
  await page.unroute(`${WP}/wp-admin/admin-ajax.php`);

  await dashboard('#github');
  await page.click('#github details summary');
  await page.fill('input[name="wpta[github_token]"]', 'ghp_test');
  await Promise.all([page.waitForNavigation(), page.click('#github .wpta-save button')]);

  // 1. The dashboard.
  await dashboard();
  await sleep(500); // OpenRouter balance.
  await page.screenshot({ path: shot(1) });

  // 2. The AI model card: ChatGPT and OpenRouter side by side in the provider picker, OpenRouter connected.
  await page.locator('#ai').screenshot({ path: shot(2) });

  // 4. A sync in progress. Steps are slowed down so the bar is caught mid-run.
  await page.route(`${WP}/wp-admin/admin-ajax.php`, async (route) => {
    if ((route.request().postData() || '').includes('action=wpta_step')) await sleep(700);
    return route.fallback();
  });
  await page.click('#wpta-start');
  await page.waitForFunction(() => /Copying media|Rebuilding|Cleaning|Packaging|Comparing|Uploading/.test(document.querySelector('.wpta-phase').textContent));
  await page.evaluate(() => { document.querySelector('.wpta-logbox').open = true; });
  await page.locator('#convert').screenshot({ path: shot(4) });
  await page.waitForFunction(() => !document.querySelector('#wpta-result').hidden, null, { timeout: 120000 });
  await page.unroute(`${WP}/wp-admin/admin-ajax.php`);

  // 5. Recent runs.
  await dashboard('#history');
  await page.locator('#history').screenshot({ path: shot(5) });

  // 6. Before and after: the same post on WordPress and on the Astro build.
  const grab = async (url) => {
    const p = await browser.newPage({ viewport: { width: 900, height: 760 } });
    await p.goto(url, { waitUntil: 'networkidle' });
    const png = (await p.screenshot()).toString('base64');
    await p.close();
    return png;
  };
  const [before, after] = [await grab(WP + POST), await grab(ASTRO + POST)];
  const board = await browser.newPage({ viewport: { width: 1840, height: 860 } });
  await board.setContent(`<!doctype html><style>
    body{margin:0;padding:24px;background:#17191e;font:600 22px Inter,system-ui,sans-serif;color:#fff;display:flex;gap:40px}
    figure{margin:0} figcaption{margin:0 0 12px} img{display:block;width:880px;border-radius:8px;box-shadow:0 8px 30px #0008}
  </style>
  <figure><figcaption>WordPress</figcaption><img src="data:image/png;base64,${before}"></figure>
  <figure><figcaption>Astro, converted by WP2Astro</figcaption><img src="data:image/png;base64,${after}"></figure>`);
  await board.screenshot({ path: shot(6), fullPage: true });

  await browser.close();
  console.log(`Screenshots 1-6 in ${out}`);
})();
