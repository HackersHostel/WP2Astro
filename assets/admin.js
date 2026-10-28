/* global WPTA */
(function () {
  const $ = (id) => document.getElementById(id);

  // Provider preset → fill base URL + model.
  const provider = $('wpta-provider');
  const toggleAi = () => {
    document.querySelectorAll('.wpta-ai-row').forEach((row) => {
      row.hidden = provider.value === 'none';
    });
  };
  if (provider) {
    provider.addEventListener('change', () => {
      const p = WPTA.presets[provider.value];
      if (p && provider.value !== 'custom' && provider.value !== 'none') {
        $('wpta-base').value = p.base_url;
        $('wpta-model').value = p.model;
      }
      toggleAi();
    });
    toggleAi();
  }

  const btn = $('wpta-start');
  if (!btn) return;
  const log = $('wpta-log');
  const result = $('wpta-result');

  const write = (lines) => {
    (lines || []).forEach((l) => { log.textContent += l + '\n'; });
    log.scrollTop = log.scrollHeight;
  };

  const post = async (action, data = {}) => {
    const body = new URLSearchParams({ action, _ajax_nonce: WPTA.nonce, ...data });
    const res = await fetch(WPTA.ajax, { method: 'POST', credentials: 'same-origin', body });
    let json;
    try { json = await res.json(); } catch { throw new Error('Server returned HTTP ' + res.status + ' (check the PHP error log).'); }
    if (!json.success) throw new Error((json.data && json.data.message) || 'Request failed.');
    return json.data;
  };

  const link = (href, text) => {
    const a = document.createElement('a');
    a.href = href; a.textContent = text; a.className = 'button';
    if (!href.includes('admin-post.php')) { a.target = '_blank'; a.rel = 'noopener'; }
    return a;
  };

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    log.hidden = false; log.textContent = '';
    result.hidden = true; result.innerHTML = '';
    try {
      const start = await post('wpta_start');
      write(start.log);
      let status;
      do {
        status = await post('wpta_step', { job: start.job });
        write(status.log);
        btn.textContent = `Converting… ${status.progress}%`;
      } while (!status.done);

      if (status.error) throw new Error('Conversion stopped. See the log above.');
      result.hidden = false;
      const p = document.createElement('p');
      p.innerHTML = '<strong>Your Astro site is ready.</strong> ';
      result.appendChild(p);
      if (status.result.zip) result.appendChild(link(status.result.zip, 'Download .zip'));
      if (status.result.repo) result.appendChild(link(status.result.repo, 'Open GitHub repo'));
    } catch (e) {
      write(['✖ ' + e.message]);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Convert to Astro';
    }
  });
})();
