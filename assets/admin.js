/* global WPTA */
(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const send = async (body) => {
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
  const post = (action, data = {}) => send(new URLSearchParams({ action, _ajax_nonce: WPTA.nonce, ...data }));
  // The whole settings form, as the admin-post handler would get it.
  const postForm = (action, form, extra = {}) => {
    const body = new URLSearchParams(new FormData(form));
    body.set('action', action);
    body.set('_ajax_nonce', WPTA.nonce);
    Object.entries(extra).forEach(([k, v]) => body.set(k, v));
    return send(body);
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

  const form = $('#wpta-settings');
  const wizard = !!(form && form.classList.contains('wpta-wizard'));
  let dirty = false;
  // Leave the page on purpose (reload, OAuth redirect): no unsaved-changes prompt.
  const go = (fn) => { dirty = false; fn(); };

  /* ---------------------------------------------------------------- Tabs */

  const tabLinks = $$('.wpta-tabs a');
  const tabPanels = $$('.wpta-tabpanel');
  const sections = ['ai', 'github', 'output', 'sync', 'data'];
  const subLinks = $$('.wpta-subnav a');
  let section = '';
  const markSub = (id) => {
    section = id;
    subLinks.forEach((a) => a.classList.toggle('is-current', a.getAttribute('href') === '#' + id));
  };
  const route = () => {
    if (!tabPanels.length) return;
    const h = window.location.hash.slice(1);
    const tab = sections.includes(h) ? 'settings' : (['overview', 'activity', 'settings'].includes(h) ? h : 'overview');
    tabLinks.forEach((a) => {
      if (a.dataset.tab === tab) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    const was = tabPanels.find((p) => !p.hidden);
    tabPanels.forEach((p) => { p.hidden = p.dataset.panel !== tab; });
    if (was && was.dataset.panel !== tab && !sections.includes(h)) window.scrollTo(0, 0);
    if (sections.includes(h)) {
      markSub(h);
      const el = document.getElementById(h);
      if (el) window.requestAnimationFrame(() => el.scrollIntoView({ block: 'start' }));
    }
  };
  window.addEventListener('hashchange', route);
  route();

  if ('IntersectionObserver' in window && subLinks.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => { if (en.isIntersecting) markSub(en.target.id); });
    }, { rootMargin: '-35% 0px -60% 0px' });
    sections.forEach((id) => { const el = document.getElementById(id); if (el) io.observe(el); });
  }

  const help = $('.wpta-help');
  if (help) help.addEventListener('click', () => {
    const link = $('#contextual-help-link');
    if (link) { link.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
  });

  $$('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    const src = $(b.dataset.copy);
    if (!src) return;
    navigator.clipboard.writeText(src.textContent).then(() => {
      const old = b.innerHTML;
      b.textContent = 'Copied';
      setTimeout(() => { b.innerHTML = old; }, 1500);
    });
  }));

  /* ------------------------------------------------------- Unsaved changes */

  const bar = $('.wpta-savebar');
  if (form && bar) {
    const text = $('.wpta-savebar-text', bar);
    const discard = $('.wpta-discard', bar);
    const mark = (e) => {
      // Sign-in helpers aren't settings.
      if (dirty || (e && e.target.closest('.wpta-cg-paste, .wpta-gh-device'))) return;
      dirty = true;
      bar.classList.add('is-dirty');
      text.textContent = 'You have unsaved changes';
      discard.hidden = false;
    };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    discard.addEventListener('click', () => go(() => window.location.reload()));
    form.addEventListener('submit', () => {
      dirty = false;
      $('#wpta-section').value = section || 'settings';
    });
    window.addEventListener('beforeunload', (e) => {
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
  }

  // Enter in a helper field must not submit the whole settings form.
  $$('.wpta-cg-callback').forEach((i) => i.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); $('.wpta-cg-finish').click(); }
  }));

  /* ------------------------------------------------------------ Providers */

  const panels = $$('.wpta-panel');
  const showPanel = (id) => {
    panels.forEach((p) => { p.hidden = p.dataset.provider !== id; });
    $$('.wpta-prov').forEach((l) => l.classList.toggle('is-selected', $('input', l).checked));
  };
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
    if (!list) return;
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
    const msg = $('.wpta-fields .wpta-msg', panel);
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

  /* ----------------------------------------------------------- OpenRouter */

  $$('.wpta-or-connect').forEach((b) => b.addEventListener('click', () => busy(b, async () => {
    try {
      const { url } = await post('wpta_or_start');
      go(() => { window.location.href = url; });
    } catch (e) { alert(e.message); }
  })));

  const balances = $$('.wpta-balance');
  const loadBalance = async () => {
    if (!balances.length) return;
    const show = (text, bad) => balances.forEach((b) => { b.textContent = text; b.classList.toggle('is-bad', !!bad); });
    show('checking credits…');
    try {
      const d = await post('wpta_or_balance');
      if (typeof d.balance === 'number') show(`$${d.balance.toFixed(2)} in credits`, d.balance < 0.5);
      else if (d.key_limit !== null && d.key_limit !== undefined) show(`$${Number(d.key_limit).toFixed(2)} left on this key`);
      else show(d.free_tier ? 'Free tier: free models only' : 'Connected');
    } catch (e) { show(e.message, true); }
  };
  loadBalance();
  const refresh = $('.wpta-balance-refresh');
  if (refresh) refresh.addEventListener('click', loadBalance);
  // Back from buying credits in another tab: show the new balance.
  document.addEventListener('visibilitychange', () => { if (!document.hidden) loadBalance(); });

  /* -------------------------------------------------------------- ChatGPT */

  const cg = $('[data-connect="chatgpt"]');
  if (cg) {
    const cgPanel = cg.closest('.wpta-panel');
    const msg = $('.wpta-fields .wpta-msg', cgPanel);
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
        say(msg, wizard ? 'Signed in. Pick a model, then click Continue.' : 'Signed in. Pick a model, then click Save settings.', 'ok');
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

  /* --------------------------------------------------------------- GitHub */

  const gh = $('[data-connect="github"]');
  if (gh) {
    const msg = $('.wpta-msg', gh);
    const back = () => go(() => { window.location.hash = 'github'; window.location.reload(); });
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
            back();
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
        back();
      } catch (e) { alert(e.message); }
    }));
  }

  /* -------------------------------------------------------- Overview bits */

  const autoNow = $('.wpta-auto-now');
  if (autoNow) autoNow.addEventListener('change', () => busy(autoNow, async () => {
    try {
      await post('wpta_auto_sync', { on: autoNow.checked ? '1' : '' });
      go(() => window.location.reload());
    } catch (e) { autoNow.checked = !autoNow.checked; alert(e.message); }
  }));

  const clear = $('.wpta-clear');
  if (clear) clear.addEventListener('click', () => busy(clear, async () => {
    if (!window.confirm('Clear the AI cache, run history and stored zips? Settings and your GitHub repo stay as they are.')) return;
    const msg = $('.wpta-clear-msg');
    try { say(msg, (await post('wpta_clear')).message, 'ok'); } catch (e) { say(msg, e.message, 'bad'); }
  }));

  /* -------------------------------------------------------------- Running */

  const view = $('#wpta-run');
  const overview = $('.wpta-overview');
  const run = (() => {
    if (!view) return null;
    const title = $('#wpta-run-title', view);
    const phase = $('.wpta-phase', view);
    const stop = $('.wpta-stop', view);
    const result = $('#wpta-result', view);
    const log = $('#wpta-log', view);
    const logbox = $('.wpta-logbox', view);
    const busyBadge = $('.wpta-run-busy', view);
    const ringBar = $('.wpta-ring-bar', view);
    const ringPct = $('.wpta-ring-pct strong', view);
    const ringLabel = $('.wpta-ring-label', view);
    const steps = $$('.wpta-phases li', view);
    const order = steps.map((li) => li.dataset.phase);
    const C = 2 * Math.PI * 64;
    const text = {
      init: 'Reading the theme and settings', content: 'Converting content to Markdown', media: 'Copying media',
      ai_layout: 'Rebuilding the layout with AI (can take a few minutes on local models)', ai_cleanup: 'Cleaning up dead shortcodes',
      package: 'Packaging the project', github: 'Comparing with GitHub', github_upload: 'Uploading changed files',
      github_commit: 'Committing', done: 'Done', error: 'Stopped',
    };
    let stopping = false;
    let seen = [];

    const ring = (p) => {
      ringBar.style.strokeDashoffset = String(C * (1 - p / 100));
      ringPct.textContent = p + '%';
    };
    const write = (lines) => {
      (lines || []).forEach((l) => {
        log.textContent += l + '\n';
        if (/rate.?limit|retrying|busy/i.test(l)) busyBadge.hidden = false;
      });
      log.scrollTop = log.scrollHeight;
    };
    const mark = (current, failed) => {
      if (current !== 'done' && current !== 'error' && !seen.some((s) => s.phase === current)) {
        seen.push({ phase: current, at: Date.now() });
        busyBadge.hidden = true;
      }
      const at = order.indexOf(current === 'error' && seen.length ? seen[seen.length - 1].phase : current);
      steps.forEach((li, i) => {
        const done = current === 'done' || (at >= 0 && i < at);
        li.classList.toggle('is-done', done);
        li.classList.toggle('is-current', i === at && !failed && current !== 'done');
        li.classList.toggle('is-failed', i === at && !!failed);
        const k = seen.findIndex((s) => s.phase === li.dataset.phase);
        const t = $('.wpta-phase-time', li);
        if (done && k >= 0 && !t.textContent) {
          const end = seen[k + 1] ? seen[k + 1].at : Date.now();
          t.textContent = ((end - seen[k].at) / 1000).toFixed(1) + ' s';
        }
      });
    };
    const button = (label, { href, icon, primary, onClick } = {}) => {
      const el = document.createElement(href ? 'a' : 'button');
      el.className = 'wpta-btn' + (primary ? ' is-primary' : '');
      if (href) {
        el.href = href;
        if (!href.includes('admin-post.php')) { el.target = '_blank'; el.rel = 'noopener'; }
      } else {
        el.type = 'button';
        el.addEventListener('click', onClick);
      }
      if (icon && WPTA.icons[icon]) el.innerHTML = WPTA.icons[icon];
      el.appendChild(document.createTextNode(label));
      return el;
    };
    const home = () => go(() => window.location.assign(window.location.pathname + window.location.search));

    stop.addEventListener('click', () => {
      stopping = true;
      stop.disabled = true;
      phase.textContent = 'Stopping after this step…';
    });

    const start = async (force) => {
      stopping = false;
      seen = [];
      view.hidden = false;
      if (overview) overview.hidden = true;
      if (wizard) {
        form.hidden = true;
        $$('.wpta-quiet-link').forEach((a) => { a.hidden = true; });
      }
      result.hidden = true;
      result.innerHTML = '';
      stop.hidden = false;
      stop.disabled = false;
      log.textContent = '';
      logbox.open = false;
      busyBadge.hidden = true;
      phase.classList.remove('is-bad');
      steps.forEach((li) => {
        const p = li.dataset.phase;
        li.hidden = (!WPTA.github && p.startsWith('github')) || (!WPTA.ai && p.startsWith('ai_'));
        li.className = '';
        $('.wpta-phase-time', li).textContent = '';
      });
      title.textContent = WPTA.github ? (WPTA.setup ? 'Converting and pushing to GitHub…' : 'Syncing with GitHub…') : 'Converting your site…';
      phase.textContent = text.init;
      ringLabel.textContent = 'starting';
      ring(0);
      mark('init');
      window.scrollTo({ top: 0, behavior: 'smooth' });

      let status;
      try {
        const begun = await post('wpta_start', force ? { force: 1 } : {});
        write(begun.log);
        do {
          if (stopping) {
            status = await post('wpta_stop', { job: begun.job });
            write(status.log);
            break;
          }
          status = await post('wpta_step', { job: begun.job });
          write(status.log);
          ring(status.progress);
          ringLabel.textContent = 'done so far';
          if (!stopping) phase.textContent = text[status.phase] || status.phase;
          mark(status.phase, status.error);
        } while (!status.done);

        if (status.error) throw new Error(status.result.error || 'The run stopped. See the log.');
        const r = status.result;
        mark('done');
        ring(100);
        ringLabel.textContent = 'done';
        stop.hidden = true;
        title.textContent = r.commit ? 'Synced to GitHub' : (r.repo ? 'Already up to date' : 'Your Astro site is ready');
        phase.textContent = r.commit ? `${r.changed} file(s) changed, ${r.deleted} removed.`
          : (r.repo ? 'Nothing changed since the last sync.' : 'Download the zip and deploy it anywhere.');
        const row = document.createElement('p');
        row.className = 'wpta-actions';
        if (r.repo) row.appendChild(button('Open repo', { href: r.repo, icon: 'github', primary: true }));
        if (r.commit) row.appendChild(button('View commit', { href: r.commit, icon: 'external' }));
        if (r.zip) row.appendChild(button('Download zip', { href: r.zip, icon: 'download', primary: !r.repo }));
        row.appendChild(button(WPTA.setup ? 'Go to the dashboard' : 'Back to the dashboard', { onClick: home }));
        result.appendChild(row);
        result.hidden = false;
      } catch (e) {
        write(['✖ ' + e.message]);
        logbox.open = true;
        stop.hidden = true;
        ringLabel.textContent = 'stopped';
        title.textContent = 'The run stopped';
        phase.textContent = e.message;
        phase.classList.add('is-bad');
        if (status) mark('error', true);
        if (e.data && e.data.busy) {
          if (window.confirm(e.message + '\n\nStart anyway?')) return start(true);
          // Leave the other run alone and put the screen back.
          view.hidden = true;
          if (overview) overview.hidden = false;
          if (wizard) form.hidden = false;
          return null;
        }
        const row = document.createElement('p');
        row.className = 'wpta-actions';
        row.appendChild(button('Try again', { icon: 'sync', primary: true, onClick: () => start(false) }));
        if (wizard) {
          row.appendChild(button('Back to setup', { onClick: () => { view.hidden = true; form.hidden = false; } }));
        } else {
          row.appendChild(button('Back to the dashboard', { onClick: home }));
        }
        result.appendChild(row);
        result.hidden = false;
      }
      return null;
    };
    return start;
  })();

  const startBtn = $('#wpta-start');
  // data-force: an abandoned run from this screen holds the lock, and the button says Start again.
  if (startBtn && run) startBtn.addEventListener('click', () => run(startBtn.dataset.force === '1'));

  /* ---------------------------------------------------------------- Setup */

  if (wizard) {
    let step = 1;
    const panes = $$('[data-pane]', form);
    const titles = $$('.wpta-step-title', form);
    const dots = $$('.wpta-steps li', form);
    const back = $('[data-wizard="back"]', form);
    const next = $('[data-wizard="next"]', form);
    const skip = $('[data-wizard="skip"]', form);
    const hint = $('[data-wizard="hint"]', form);
    const msg = $('[data-wizard="msg"]', form);

    const show = (n) => {
      step = n;
      panes.forEach((p) => { p.hidden = Number(p.dataset.pane) !== n; });
      titles.forEach((t) => { t.hidden = Number(t.dataset.for) !== n; });
      dots.forEach((d) => {
        const k = Number(d.dataset.for);
        d.classList.toggle('is-current', k === n);
        d.classList.toggle('is-done', k < n);
        $('.n', d).textContent = k < n ? '✓' : String(k);
      });
      $('.wpta-step-n', form).textContent = String(n);
      back.hidden = n === 1;
      hint.hidden = n !== 1;
      skip.hidden = !(n === 2 && !WPTA.github);
      next.textContent = n === 3 ? 'Convert to Astro' : 'Continue';
      window.history.replaceState(null, '', '#step-' + n);
    };
    const save = async (extra = {}) => {
      const d = await postForm('wpta_save', form, extra);
      WPTA.ai = d.ai;
      WPTA.github = d.github;
      Object.entries(d.summary).forEach(([k, v]) => {
        const el = $(`[data-summary="${k}"]`, form);
        if (el) el.textContent = v;
      });
      const auto = $('#wpta-t-auto_sync', form);
      if (auto && d.github) auto.disabled = false;
      const picked = $('.wpta-prov input:checked', form);
      const tile = $('[data-summary-tile="ai"]', form);
      if (picked && tile) tile.innerHTML = $('.wpta-prov-art .wpta-tile', picked.closest('.wpta-prov')).outerHTML;
      return d;
    };
    const advance = (to) => busy(next, async () => {
      say(msg, '');
      try {
        if (step < 3) {
          await save();
          show(to || step + 1);
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
        }
        await save({ finish_setup: 1 });
        run(false);
      } catch (e) { say(msg, e.message, 'bad'); }
    });
    next.addEventListener('click', () => advance());
    skip.addEventListener('click', () => advance(3));
    back.addEventListener('click', () => show(step - 1));

    const h = window.location.hash.slice(1);
    show(h === 'github' || h === 'step-2' ? 2 : (h === 'step-3' ? 3 : 1));
  }
})();
