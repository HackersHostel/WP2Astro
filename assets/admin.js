/* global WPTA */
(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const post = async (action, data = {}) => {
    const body = new URLSearchParams({ action, _ajax_nonce: WPTA.nonce, ...data });
    const res = await fetch(WPTA.ajax, { method: 'POST', credentials: 'same-origin', body });
    let json;
    try { json = await res.json(); } catch { throw new Error('Server returned HTTP ' + res.status + ' (check the PHP error log).'); }
    if (!json.success) {
      const err = new Error((json.data && json.data.message) || 'Request failed.');
      err.data = json.data;
      throw err;
    }
    return json.data;
  };

  const say = (el, text, kind = '') => {
    if (!el) return;
    el.textContent = text;
    el.className = el.className.replace(/\bis-(ok|bad)\b/g, '').trim() + (kind ? ' is-' + kind : '');
  };

  const busy = async (btn, fn) => {
    if (btn.disabled) return;
    btn.disabled = true;
    try { await fn(); } finally { btn.disabled = false; }
  };

  /* ------------------------------------------------------------ Settings */

  // Remember which card's Save was clicked so the page comes back to it.
  $$('.wpta-save button').forEach((b) => b.addEventListener('click', () => { $('#wpta-section').value = b.dataset.section; }));

  // Enter in a helper field must not submit the whole settings form.
  $$('.wpta-cg-callback').forEach((i) => i.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); $('.wpta-cg-finish').click(); }
  }));

  const panels = $$('.wpta-panel');
  const showPanel = (id) => panels.forEach((p) => { p.hidden = p.dataset.provider !== id; });
  $$('input[name="wpta[provider]"]').forEach((r) => r.addEventListener('change', () => showPanel(r.value)));

  const panelData = (panel) => ({
    provider: panel.dataset.provider,
    base_url: ($('.wpta-base', panel) || {}).value || '',
    api_key: ($('.wpta-key', panel) || {}).value || '',
    model: ($('.wpta-model', panel) || {}).value || '',
  });

  const fillModels = (panel, models) => {
    const list = $('datalist', panel);
    const input = $('.wpta-model', panel);
    list.innerHTML = '';
    models.forEach((m) => {
      const o = document.createElement('option');
      o.value = m.id;
      o.label = (m.label && m.label !== m.id ? m.label : '') + (m.free ? ' · free' : '');
      list.appendChild(o);
    });
    if (input && !input.value && models[0]) input.value = models[0].id;
  };

  panels.forEach((panel) => {
    const msg = $('.wpta-msg', panel);
    const load = $('.wpta-load-models', panel);
    const test = $('.wpta-test', panel);
    if (load) load.addEventListener('click', () => busy(load, async () => {
      say(msg, 'Loading models…');
      try {
        const d = await post('wpta_models', panelData(panel));
        fillModels(panel, d.models);
        const free = d.models.filter((m) => m.free).length;
        say(msg, `${d.models.length} models available${free ? ` (${free} free)` : ''}. Click the Model field to pick one.`, 'ok');
      } catch (e) { say(msg, e.message, 'bad'); }
    }));
    if (test) test.addEventListener('click', () => busy(test, async () => {
      say(msg, 'Sending a test message…');
      try { say(msg, (await post('wpta_test', panelData(panel))).message, 'ok'); } catch (e) { say(msg, e.message, 'bad'); }
    }));
  });

  /* ---------------------------------------------------------- OpenRouter */

  $$('.wpta-or-connect').forEach((b) => b.addEventListener('click', () => busy(b, async () => {
    try { window.location.href = (await post('wpta_or_start')).url; } catch (e) { alert(e.message); }
  })));

  const balance = $('.wpta-balance');
  const loadBalance = async () => {
    if (!balance) return;
    balance.textContent = 'checking credits…';
    try {
      const d = await post('wpta_or_balance');
      if (typeof d.balance === 'number') balance.textContent = `$${d.balance.toFixed(2)} in credits`;
      else if (d.key_limit !== null && d.key_limit !== undefined) balance.textContent = `$${Number(d.key_limit).toFixed(2)} left on this key`;
      else balance.textContent = d.free_tier ? 'free tier: free models only' : 'connected';
      if (typeof d.balance === 'number' && d.balance < 0.5) balance.classList.add('is-bad');
    } catch (e) { balance.textContent = e.message; balance.classList.add('is-bad'); }
  };
  loadBalance();
  const refresh = $('.wpta-balance-refresh');
  if (refresh) refresh.addEventListener('click', loadBalance);
  // Back from buying credits in another tab: show the new balance.
  document.addEventListener('visibilitychange', () => { if (!document.hidden) loadBalance(); });

  /* ------------------------------------------------------------- ChatGPT */

  const cg = $('[data-connect="chatgpt"]');
  if (cg) {
    const cgPanel = cg.closest('.wpta-panel');
    const msg = $('.wpta-msg', cgPanel);
    const start = $('.wpta-cg-start', cg);
    start.addEventListener('click', () => busy(start, async () => {
      try {
        const { url } = await post('wpta_cg_start');
        window.open(url, '_blank', 'noopener');
        $('.wpta-cg-paste', cg).hidden = false;
        $('.wpta-cg-callback', cg).focus();
      } catch (e) { say(msg, e.message, 'bad'); }
    }));
    const finish = $('.wpta-cg-finish', cg);
    finish.addEventListener('click', () => busy(finish, async () => {
      say(msg, 'Finishing sign-in…');
      try {
        const d = await post('wpta_cg_finish', { callback: $('.wpta-cg-callback', cg).value });
        $('.wpta-cg-on', cg).hidden = false;
        $('.wpta-cg-off', cg).hidden = true;
        $('.wpta-cg-email', cg).textContent = d.status.email ? ' as ' + d.status.email : '';
        fillModels(cgPanel, d.models);
        say(msg, 'Signed in. Pick a model, then Save settings.', 'ok');
      } catch (e) { say(msg, e.message, 'bad'); }
    }));
    const out = $('.wpta-cg-signout', cg);
    out.addEventListener('click', () => busy(out, async () => {
      try {
        say(msg, (await post('wpta_cg_disconnect')).message, 'ok');
        $('.wpta-cg-on', cg).hidden = true;
        $('.wpta-cg-off', cg).hidden = false;
      } catch (e) { say(msg, e.message, 'bad'); }
    }));
  }

  /* -------------------------------------------------------------- GitHub */

  const gh = $('[data-connect="github"]');
  if (gh) {
    const msg = $('.wpta-msg', gh);
    const start = $('.wpta-gh-start', gh);
    if (start) start.addEventListener('click', () => busy(start, async () => {
      try {
        const d = await post('wpta_gh_start');
        $('.wpta-gh-code', gh).textContent = d.user_code;
        $('.wpta-gh-open', gh).href = d.verification_uri;
        $('.wpta-gh-device', gh).hidden = false;
        start.hidden = true;
        let wait = d.interval;
        const until = Date.now() + d.expires_in * 1000;
        const poll = async () => {
          if (Date.now() > until) { say(msg, 'The code expired. Reload and try again.', 'bad'); return; }
          try {
            const r = await post('wpta_gh_poll');
            if (r.pending) {
              if (r.interval) wait = r.interval;
              setTimeout(poll, wait * 1000);
              return;
            }
            say(msg, `Connected as @${r.login}.`, 'ok');
            window.location.hash = 'github';
            window.location.reload();
          } catch (e) { say(msg, e.message, 'bad'); }
        };
        setTimeout(poll, wait * 1000);
      } catch (e) { say(msg, e.message, 'bad'); }
    }));
    const copy = $('.wpta-copy', gh);
    if (copy) copy.addEventListener('click', () => {
      navigator.clipboard.writeText($('.wpta-gh-code', gh).textContent).then(() => { copy.textContent = 'Copied'; });
    });
    const check = $('.wpta-gh-check', gh);
    if (check) check.addEventListener('click', () => busy(check, async () => {
      const status = $('.wpta-gh-status', gh);
      say(status, 'Checking…');
      try {
        const d = await post('wpta_gh_check');
        if (!d.repo) say(status, `Token works (@${d.login}). The repo will be created on the first run.`, 'ok');
        else say(status, `${d.repo.name} (${d.repo.private ? 'private' : 'public'})${d.repo.pushed ? ', last push ' + new Date(d.repo.pushed).toLocaleString() : ''}.`, 'ok');
      } catch (e) { say(status, e.message, 'bad'); }
    }));
    const disc = $('.wpta-gh-disconnect', gh);
    if (disc) disc.addEventListener('click', () => busy(disc, async () => {
      if (!window.confirm('Disconnect GitHub? The repo itself is not touched.')) return;
      try {
        alert((await post('wpta_gh_disconnect')).message);
        window.location.hash = 'github';
        window.location.reload();
      } catch (e) { alert(e.message); }
    }));
  }

  /* ------------------------------------------------------------- Convert */

  const btn = $('#wpta-start');
  if (!btn) return;
  const label = btn.textContent;
  const log = $('#wpta-log');
  const logbox = $('.wpta-logbox');
  const result = $('#wpta-result');
  const progress = $('.wpta-progress');
  const bar = $('.wpta-bar span');
  const phase = $('.wpta-phase');
  const phases = {
    init: 'Reading the theme and settings', content: 'Converting content to Markdown', media: 'Copying media',
    ai_layout: 'Rebuilding the layout with AI', ai_cleanup: 'Cleaning up dead shortcodes',
    package: 'Packaging the project', github: 'Comparing with GitHub', github_upload: 'Uploading changed files',
    github_commit: 'Committing', done: 'Done', error: 'Stopped',
  };

  const write = (lines) => {
    (lines || []).forEach((l) => { log.textContent += l + '\n'; });
    log.scrollTop = log.scrollHeight;
  };

  const link = (href, text, primary) => {
    const a = document.createElement('a');
    a.href = href; a.textContent = text; a.className = 'button' + (primary ? ' button-primary' : '');
    if (!href.includes('admin-post.php')) { a.target = '_blank'; a.rel = 'noopener'; }
    return a;
  };

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const ago = (t) => {
    const s = Math.max(1, Math.round(Date.now() / 1000 - t));
    if (s < 60) return s + ' sec ago';
    if (s < 3600) return Math.round(s / 60) + ' min ago';
    if (s < 86400) return Math.round(s / 3600) + ' hours ago';
    return Math.round(s / 86400) + ' days ago';
  };
  const renderRuns = (runs) => {
    const how = { manual: 'Button', auto: 'Auto-sync', cli: 'WP-CLI' };
    $('#wpta-runs').innerHTML = runs.map((r) => {
      let res;
      if (r.status === 'error') res = `<span class="wpta-bad">Failed</span> ${esc((r.error || '').slice(0, 120))}`;
      else if (r.status === 'running') res = '<span class="wpta-muted">Running…</span>';
      else {
        const parts = [`${r.entries || 0} entries`];
        if (r.changed !== null && r.changed !== undefined) {
          parts.push(`${r.changed} changed`);
          if (r.deleted) parts.push(`${r.deleted} removed`);
          if (r.kept && r.kept.length) parts.push(`${r.kept.length} kept`);
        }
        res = `<span class="wpta-good">Done</span> ${esc(parts.join(', '))}`;
      }
      const links = (r.commit ? `<a href="${esc(r.commit)}" target="_blank" rel="noopener">Commit ↗</a> ` : '') + (r.zip ? `<a href="${esc(r.zip)}">Zip</a>` : '');
      return `<tr><td>${ago(r.started)}</td><td>${how[r.trigger] || ''}</td><td>${res}</td><td class="wpta-links">${links}</td></tr>`;
    }).join('') || '<tr><td colspan="4" class="wpta-muted">No runs yet.</td></tr>';
  };

  const run = async (force) => {
    btn.disabled = true;
    logbox.hidden = false; log.textContent = '';
    result.hidden = true; result.innerHTML = '';
    progress.hidden = false; bar.style.width = '2%'; phase.textContent = phases.init;
    let status;
    try {
      const start = await post('wpta_start', force ? { force: 1 } : {});
      write(start.log);
      do {
        status = await post('wpta_step', { job: start.job });
        write(status.log);
        bar.style.width = Math.max(2, status.progress) + '%';
        phase.textContent = (phases[status.phase] || status.phase) + ` · ${status.progress}%`;
        btn.textContent = `Working… ${status.progress}%`;
      } while (!status.done);

      if (status.error) throw new Error(status.result.error || 'Conversion stopped. See the log.');
      result.hidden = false;
      const p = document.createElement('p');
      const r = status.result;
      p.innerHTML = r.commit ? '<strong>Synced.</strong> ' + esc(`${r.changed} file(s) changed, ${r.deleted} removed.`)
        : r.repo ? '<strong>Already up to date.</strong> Nothing changed since the last sync.'
          : '<strong>Your Astro site is ready.</strong>';
      result.appendChild(p);
      if (r.repo) result.appendChild(link(r.repo, 'Open repo ↗', true));
      if (r.commit) result.appendChild(link(r.commit, 'View commit ↗'));
      if (r.zip) result.appendChild(link(r.zip, 'Download .zip', !r.repo));
      logbox.open = false;
    } catch (e) {
      write(['✖ ' + e.message]);
      logbox.open = true;
      phase.textContent = e.message;
      phase.classList.add('is-bad');
      if (e.data && e.data.busy && window.confirm(e.message + '\n\nStart anyway?')) {
        btn.disabled = false;
        return run(true);
      }
    } finally {
      btn.disabled = false;
      btn.textContent = label;
      post('wpta_runs').then((d) => renderRuns(d.runs)).catch(() => {});
    }
  };
  btn.addEventListener('click', () => { phase.classList.remove('is-bad'); run(false); });
})();
