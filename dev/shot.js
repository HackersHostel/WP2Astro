const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1100, height: 900 } });
  for (const [name, url] of [['wp','http://localhost:8899/2009/10/the-lighthouse-keeper-who-never-left/'],['astro','http://localhost:4567/2009/10/the-lighthouse-keeper-who-never-left/'],['astro-home','http://localhost:4567/']]) {
    await p.goto(url, { waitUntil: 'networkidle' }); await p.screenshot({ path: `${name}.png` });
  }
  await b.close();
})();
