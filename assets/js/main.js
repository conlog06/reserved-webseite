(() => {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const root = document.documentElement;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const LIVE = root.hasAttribute('data-cms-live');          // vom Server (PHP) ausgeliefert?
  const API = root.dataset.api || '_reserved/api.php';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------- Herkunft merken (für Anfragen) ---------- */
  try {
    if (!sessionStorage.getItem('rs-src')) {
      const u = new URLSearchParams(location.search);
      let src = u.get('utm_source') || '';
      if (!src && document.referrer) { try { const h = new URL(document.referrer).hostname; if (h !== location.hostname) src = h.replace(/^www\./, ''); } catch (e) {} }
      if (!src && /Instagram/i.test(navigator.userAgent)) src = 'Instagram';
      sessionStorage.setItem('rs-src', src || 'direkt');
    }
  } catch (e) {}
  const source = () => { try { return sessionStorage.getItem('rs-src') || 'direkt'; } catch (e) { return 'direkt'; } };

  /* ---------- Navigation ---------- */
  const nav = $('#nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 30);
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  const burger = $('#burger');
  let lockY = 0;
  const FX = () => window.RsFX || null;
  const lock = (on) => {
    if (FX()?.lenis) { FX().lock(on); root.classList.toggle('dlg-open', on); return; }
    if (on) { lockY = scrollY; document.body.style.cssText = `position:fixed;top:-${lockY}px;left:0;right:0`; }
    else { document.body.style.cssText = ''; scrollTo(0, lockY); }
  };
  const goTo = (sel) => { const el = typeof sel === 'string' ? $(sel) : sel; if (!el) return; FX() ? FX().scrollTo(el) : el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); };
  const toggleMenu = (open) => {
    if (open === document.body.classList.contains('menu-open')) return;
    document.body.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', open);
    burger.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    lock(open);
    FX()?.menu?.(open);
  };
  if (burger) burger.addEventListener('click', () => toggleMenu(!document.body.classList.contains('menu-open')));
  $$('#mobile-menu a').forEach(a => a.addEventListener('click', (e) => {
    const target = a.getAttribute('href');
    toggleMenu(false);
    if (target && target.startsWith('#')) { e.preventDefault(); setTimeout(() => goTo(target), 60); }
  }));
  addEventListener('keydown', e => { if (e.key === 'Escape') toggleMenu(false); });
  matchMedia('(min-width: 1021px)').addEventListener?.('change', ev => { if (ev.matches) toggleMenu(false); });

  /* ---------- Einblenden beim Scrollen ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
  if (!root.classList.contains('fx')) $$('.reveal').forEach(el => io.observe(el));

  /* ---------- Handy-Mockup: Story-Slider ---------- */
  const slides = $$('#igSlides img');
  const bars = $('#igBars');
  if (slides.length && bars) {
    const DUR = 3200;
    slides.forEach(() => bars.appendChild(document.createElement('i')));
    const barEls = $$('i', bars);
    let cur = 0, timer, visible = true;
    // Bilder erst laden, wenn sie gebraucht werden
    const load = (img) => { if (img && img.dataset.src) { if (img.dataset.srcset) img.srcset = img.dataset.srcset; img.src = img.dataset.src; delete img.dataset.src; } };
    const show = (n) => {
      cur = (n + slides.length) % slides.length;
      load(slides[cur]);
      setTimeout(() => load(slides[(cur + 1) % slides.length]), 1200);
      slides.forEach((s, i) => s.classList.toggle('on', i === cur));
      barEls.forEach((b, i) => { b.classList.remove('active'); b.classList.toggle('done', i < cur); });
      void barEls[cur].offsetWidth;
      if (!reduce) barEls[cur].classList.add('active');
      clearTimeout(timer);
      if (visible) timer = setTimeout(() => show(cur + 1), DUR);
    };
    bars.style.setProperty('--dur', DUR + 'ms');
    $('#igSlides').addEventListener('click', e => {
      const r = e.currentTarget.getBoundingClientRect();
      show(e.clientX - r.left < r.width / 3 ? cur - 1 : cur + 1);
    });
    // Nur laufen lassen, wenn sichtbar (spart Akku am Handy)
    new IntersectionObserver(([en]) => { visible = en.isIntersecting; clearTimeout(timer); if (visible) show(cur); }).observe($('.phone'));
  }

  /* ---------- Instagram: Raster + Großansicht ---------- */
  let ig = { profile: {}, posts: [] };
  try { ig = JSON.parse($('#igData')?.textContent || '{}'); } catch (e) {}
  const box = $('#igBox');
  const fmtDate = (ts) => ts ? new Date(ts * 1000).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
  const fmtNum = (n) => Number(n).toLocaleString('de-DE');
  const caption = (t) => esc(t).replace(/(^|\s)([#@][\wÄÖÜäöüß.]+)/g, '$1<span class="tag">$2</span>');
  let postIdx = 0;
  const openPost = (i) => {
    const p = ig.posts?.[i]; if (!p || !box) return;
    postIdx = i;
    const items = p.slides?.length ? p.slides : [{ img: p.img, video: p.video }];
    const track = $('#igTrack');
    track.innerHTML = items.map((s, k) => `<div>${s.video
      ? `<video src="${esc(s.video)}" poster="${esc(s.img)}" controls playsinline preload="none"></video>`
      : `<img src="${esc(s.img)}" alt="Bild ${k + 1} von ${items.length}" decoding="async">`}</div>`).join('');
    track.scrollLeft = 0;
    $('#igDots').innerHTML = items.length > 1 ? items.map((_, k) => `<i class="${k ? '' : 'on'}"></i>`).join('') : '';
    $('#igPrev').hidden = $('#igNext').hidden = items.length < 2;
    $('#igBoxCap').innerHTML = caption(p.caption || '');
    const meta = [];
    if (p.likes != null) meta.push(`<span><svg><use href="#i-heart"/></svg>${fmtNum(p.likes)}</span>`);
    if (p.comments != null) meta.push(`<span><svg><use href="#i-comment"/></svg>${fmtNum(p.comments)}</span>`);
    if (p.ts) meta.push(`<span>${fmtDate(p.ts)}</span>`);
    $('#igBoxMeta').innerHTML = meta.join('');
    $('#igBoxMeta').hidden = !meta.length;
    $('#igBoxLink').href = p.permalink || `https://www.instagram.com/${ig.profile?.username || 'reserved.social'}/`;
    $('#igPostPrev').disabled = i === 0;
    $('#igPostNext').disabled = i >= ig.posts.length - 1;
    if (!box.open) { box.showModal(); root.classList.add('dlg-open'); FX()?.lock(true); }
  };
  if (box) {
    const trk = $('#igTrack');
    const step = (d) => trk.scrollBy({ left: d * trk.clientWidth, behavior: reduce ? 'auto' : 'smooth' });
    $('#igPrev').addEventListener('click', () => step(-1));
    $('#igNext').addEventListener('click', () => step(1));
    $('#igPostPrev').addEventListener('click', () => openPost(postIdx - 1));
    $('#igPostNext').addEventListener('click', () => openPost(postIdx + 1));
    trk.addEventListener('scroll', () => {
      const k = Math.round(trk.scrollLeft / Math.max(1, trk.clientWidth));
      $$('#igDots i').forEach((d, j) => d.classList.toggle('on', j === k));
      $$('video', trk).forEach((v, j) => { if (j !== k) v.pause(); });
    }, { passive: true });
    box.addEventListener('keydown', e => {
      if (e.key === 'ArrowRight') { e.preventDefault(); (trk.scrollLeft + trk.clientWidth < trk.scrollWidth - 4) ? step(1) : openPost(Math.min(postIdx + 1, ig.posts.length - 1)); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); trk.scrollLeft > 4 ? step(-1) : openPost(Math.max(postIdx - 1, 0)); }
    });
    box.addEventListener('close', () => { root.classList.remove('dlg-open'); $$('video', box).forEach(v => v.pause()); });
  }
  $$('#igGrid .tile').forEach(t => t.addEventListener('click', () => { openPost(+t.dataset.i); track('ig-post'); }));
  // Reels: beim Drüberfahren stumm abspielen (nur eigene Videos, nichts von Instagram nachladen)
  if (matchMedia('(hover: hover)').matches && !reduce) $$('#igGrid .tile[data-preview]').forEach(t => {
    let v = null;
    t.addEventListener('mouseenter', () => {
      if (!v) { v = document.createElement('video'); v.className = 'tile-vid'; v.muted = true; v.loop = true; v.playsInline = true; v.preload = 'auto'; v.src = t.dataset.preview; t.appendChild(v); }
      v.play().then(() => t.classList.add('playing')).catch(() => {});
    });
    t.addEventListener('mouseleave', () => { if (v) { v.pause(); t.classList.remove('playing'); } });
  });
  // Kacheln im Startbereich öffnen dieselbe Großansicht
  $$('[data-ig-open]').forEach(t => t.addEventListener('click', () => { openPost(+t.dataset.igOpen); track('ig-hero'); }));

  /* ---------- Startbereich: "Diese Woche" – heutiger Tag ---------- */
  const today = (new Date().getDay() + 6) % 7; // Mo = 0
  $$('#weekDays li').forEach((li, i) => li.classList.toggle('today', i === today));

  /* ---------- Rhythmus: Monate & Kalenderwoche passend zu heute ---------- */
  const kw = (d) => { const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const n = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - n); return Math.ceil(((t - Date.UTC(t.getUTCFullYear(), 0, 1)) / 864e5 + 1) / 7); };
  if ($('#rhKw')) $('#rhKw').textContent = kw(new Date());
  const months = $('#rhMonths');
  if (months) {
    const M = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    months.innerHTML = Array.from({ length: 12 }, (_, i) => {
      const d = new Date(Date.now() + (-26 + i * 52 / 12 + 2) * 7 * 864e5);
      const n = M[d.getMonth()] + (d.getMonth() === 0 ? ' ’' + String(d.getFullYear()).slice(2) : '');
      return `<span><b>${n}</b><i>${n[0]}</i></span>`;
    }).join('');
  }

  /* ---------- Rhythmus: Wochen anfahren / antippen ---------- */
  const chart = $('#rhChart');
  if (chart) {
    const plot = $('.chart-plot', chart), off = $$('.track-off .dot', chart), on = $$('.track-on .dot', chart);
    const tip = $('#rhTip'), cur = $('#rhCursor'), range = $('#rhRange'), N = on.length, NOW = on.findIndex(d => d.classList.contains('now'));
    const MS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    const monday = new Date(); monday.setHours(12, 0, 0, 0); monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
    const st = (i) => {
      const o = off[i].classList.contains('ink') ? ['ink', 'Beitrag online'] : off[i].classList.contains('zz') ? ['off', i > NOW ? 'nichts geplant' : 'Funkstille'] : ['off', 'kein Beitrag'];
      const n = on[i].classList.contains('plan') ? ['plan', 'Beitrag geplant'] : ['on', 'Beitrag online'];
      return [o, n];
    };
    const counts = (i) => {
      const upto = Math.min(i, NOW), w = upto + 1;
      const a = off.slice(0, upto + 1).filter(d => d.classList.contains('ink')).length;
      const b = on.slice(0, upto + 1).filter(d => d.classList.contains('on')).length;
      const plan = i > NOW ? on.slice(NOW + 1, i + 1).filter(d => d.classList.contains('plan')).length : 0;
      $('#rhCntOff').innerHTML = `<b>${a}</b> von ${w} Wochen mit Beitrag` + (i > NOW ? ' <small>+ 0 geplant</small>' : '');
      $('#rhCntOn').innerHTML = `<b>${b}</b> von ${w} Wochen mit Beitrag` + (plan ? ` <small>+ ${plan} geplant</small>` : '');
    };
    let last = -1;
    const show = (i, act = true) => {
      i = Math.max(0, Math.min(N - 1, i));
      chart.classList.toggle('act', act);
      if (i === last) return; last = i;
      [...off, ...on].forEach(d => d.classList.remove('hl'));
      off[i].classList.add('hl'); on[i].classList.add('hl');
      const pr = plot.getBoundingClientRect(), dr = on[i].getBoundingClientRect();
      const x = dr.left + dr.width / 2 - pr.left;
      cur.style.left = x + 'px';
      const d0 = new Date(monday.getTime() + (i - NOW) * 7 * 864e5), d1 = new Date(d0.getTime() + 6 * 864e5);
      const [o, n] = st(i);
      tip.innerHTML = `<b>${i === NOW ? 'Diese Woche' : 'KW ' + kw(d0)}</b><span class="tip-d">${d0.getDate()}. ${d0.getMonth() !== d1.getMonth() ? MS[d0.getMonth()] + ' ' : ''}– ${d1.getDate()}. ${MS[d1.getMonth()]}</span>`
        + `<span class="tip-r"><i class="dot ${o[0]}"></i>Ohne Rhythmus: ${o[1]}</span><span class="tip-r"><i class="dot ${n[0]}"></i>Mit reserved.social: ${n[1]}</span>`;
      const tw = tip.offsetWidth || 230, lx = Math.max(tw / 2, Math.min(pr.width - tw / 2, x));
      tip.style.left = lx + 'px'; tip.style.setProperty('--ax', (x - lx + tw / 2) + 'px');
      range.value = i; counts(i);
    };
    const idx = (e) => { const r = $('.track-on', chart).getBoundingClientRect(); return Math.floor((e.clientX - r.left) / r.width * N); };
    plot.addEventListener('pointermove', (e) => { if (e.pointerType === 'mouse' || e.buttons) show(idx(e)); });
    plot.addEventListener('pointerdown', (e) => { show(idx(e)); track('rh-chart'); });
    plot.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') { chart.classList.remove('act'); [...off, ...on].forEach(d => d.classList.remove('hl')); last = -1; counts(NOW); } });
    range.addEventListener('input', () => show(+range.value));
    range.addEventListener('focus', () => show(+range.value));
    range.addEventListener('blur', () => chart.classList.remove('act'));
    counts(NOW);
  }

  /* ---------- Vergleich: Initialen der Kundenstimme ---------- */
  const caseName = $('.case [data-cms="case_name"]'), caseAv = $('.case-av');
  if (caseName && caseAv) caseAv.textContent = caseName.textContent.trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();

  /* ---------- Kontakt: "Reserviert für …" zeigt den eingetippten Betrieb ---------- */
  const slotName = $('#slotName'), firma = $('#f-firma');
  if (slotName && firma) firma.addEventListener('input', () => {
    const v = firma.value.trim();
    slotName.textContent = v || 'Name des Betriebs';
    slotName.classList.toggle('filled', !!v);
  });

  /* ---------- Feedback: Filter & "mehr" ---------- */
  const wall = $('#wall');
  $$('.chip[data-filter]').forEach(chip => chip.addEventListener('click', () => {
    $$('.chip[data-filter]').forEach(c => c.setAttribute('aria-pressed', c === chip));
    const f = chip.dataset.filter;
    if (f !== 'all') wall?.classList.add('expanded');
    $$('#wall .quote').forEach(q => {
      const hide = f !== 'all' && q.dataset.client !== f;
      q.classList.toggle('hide', hide);
      if (!hide) { q.classList.add('in'); q.style.opacity = ''; q.style.visibility = ''; q.style.transform = ''; }
    });
    FX()?.refresh();
  }));
  $('#moreBtn')?.addEventListener('click', () => { wall.classList.add('expanded'); $$('#wall .quote').forEach(q => { q.classList.add('in'); q.style.opacity = ''; q.style.visibility = ''; }); FX()?.refresh(); });

  /* ---------- Pakete: Umschalter (Handy) ---------- */
  const plans = $('#plans');
  $$('.plan-switch button').forEach(b => b.addEventListener('click', () => {
    $$('.plan-switch button').forEach(x => x.setAttribute('aria-selected', x === b));
    plans.dataset.show = b.dataset.plan;
  }));

  /* ---------- Smarte Anfrage ---------- */
  const form = $('#leadForm');
  const stepsEl = form ? $$('.lf-step', form) : [];
  const labels = ['Dein Unternehmen', 'Deine Ziele', 'Deine Kontaktdaten'];
  let step = 1, started = 0;
  const val = (n) => form.elements[n]?.value?.trim?.() ?? '';
  const radio = (n) => form.querySelector(`input[name="${n}"]:checked`)?.value || '';
  const checks = (n) => $$(`input[name="${n}"]:checked`, form).map(i => i.value);

  // Dieselbe Logik wie in _reserved/leads.php
  const recommend = () => ({
    test: ['Einzelnes Video', 'Zum Reinschnuppern ohne laufende Betreuung – du siehst, wie reserved.social arbeitet.'],
    coaching: ['Strategie & 1:1 Coaching', 'Du lernst, selbst Content zu erstellen und mit CapCut zu schneiden – mit Strategie und Hilfe beim Dreh.'],
    content: ['Content-Erstellung', 'Wir planen und drehen 9:16 Content bei dir vor Ort – posten kannst du selbst.'],
    all: ['Social Media Management', 'Wir übernehmen alles: Strategie, Dreh, Posten und Community.']
  }[radio('umfang')] || ['Individuell', 'Im kostenlosen Erstgespräch stellen wir zusammen, was zu dir passt.']);
  const updateRec = () => {
    const any = checks('ziele').length || radio('umfang') || radio('stand');
    $('#lfRec').hidden = !any;
    if (!any) return;
    const [n, w] = recommend();
    $('#lfRecName').textContent = n; $('#lfRecWhy').textContent = w;
  };
  const goStep = (n, focus = true) => {
    step = n;
    stepsEl.forEach(s => { s.hidden = +s.dataset.step !== n; });
    $('#lfBar').style.transform = `scaleX(${n / 3})`;
    $('#lfLabel').textContent = `Schritt ${n} von 3 · ${labels[n - 1]}`;
    if (n === 2) updateRec();
    if (n >= 2) track('form-step' + n);
    if (focus) {
      const r = form.getBoundingClientRect();
      if (r.top < 60 || r.top > innerHeight * .4) goTo(form);
    }
  };
  const showErr = (msg) => { const el = $('#lfError'); el.textContent = msg; el.hidden = !msg; };
  if (form) {
    form.addEventListener('focusin', () => { if (!started) { started = Date.now(); track('form-start'); } }, { once: false });
    form.addEventListener('change', (e) => {
      if (!started) { started = Date.now(); track('form-start'); }
      const t = e.target;
      if (t.name === 'branche') { $('[data-if="branche=andere"]', form).hidden = t.value !== 'andere'; t.closest('.opts')?.classList.remove('err'); }
      if (['ziele', 'stand', 'umfang', 'start'].includes(t.name)) updateRec();
      if (t.name === 'consent') t.closest('.consent').classList.remove('err');
      // Ziele: höchstens zwei
      if (t.name === 'ziele') {
        const on = checks('ziele');
        $$('input[name="ziele"]', form).forEach(i => { i.disabled = !i.checked && on.length >= 2; i.closest('.opt').classList.toggle('off', i.disabled); });
      }
      // Kanal: Instagram / TikTok oder "Noch kein Profil"
      if (t.name === 'kanal') {
        const none = form.querySelector('input[name="kanal"][value="none"]');
        if (t.value === 'none' && t.checked) $$('input[name="kanal"]', form).forEach(i => { if (i !== t) i.checked = false; });
        else if (t.checked) none.checked = false;
        kanalUi();
      }
    });
    const kanalUi = () => {
      const k = checks('kanal'), inp = $('#f-insta'), none = k.includes('none');
      inp.hidden = none; inp.required = !none;
      inp.placeholder = k.includes('tiktok') && !k.includes('instagram') ? '@deintiktok' : (k.length > 1 ? '@deinprofil (Instagram / TikTok)' : '@deinunternehmen');
      inp.classList.remove('err');
    };
    kanalUi();
    $('#f-ort').addEventListener('input', (e) => {
      const v = e.target.value.trim().toLowerCase();
      $('#lfFar').hidden = !(v.length > 2 && !v.includes('osnabr') && !/^490\d\d/.test(v));
    });
    $$('[data-next]', form).forEach(b => b.addEventListener('click', () => {
      if (step === 1 && !radio('branche')) { $('[data-required="branche"]', form).classList.add('err'); return; }
      if (step === 1) {
        const k = checks('kanal'), inp = $('#f-insta');
        if (!k.length) { $('[data-kanal]', form).classList.add('err'); return; }
        if (!k.includes('none') && !inp.value.trim()) { inp.classList.add('err'); inp.focus(); return; }
        $('[data-kanal]', form).classList.remove('err');
      }
      goStep(step + 1);
    }));
    $$('[data-back]', form).forEach(b => b.addEventListener('click', () => goStep(step - 1)));
    goStep(1, false);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      showErr('');
      const name = form.elements.name, mail = form.elements.email, consent = form.elements.consent;
      name.classList.toggle('err', !name.value.trim());
      mail.classList.toggle('err', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail.value.trim()));
      consent.closest('.consent').classList.toggle('err', !consent.checked);
      if (!name.value.trim()) return name.focus();
      if (mail.classList.contains('err')) return mail.focus();
      if (!consent.checked) return showErr('Bitte bestätige noch kurz den Hinweis zum Datenschutz.');

      const data = {
        name: val('name'), email: val('email'), telefon: val('telefon'), firma: val('firma'), ort: val('ort'), insta: checks('kanal').includes('none') ? '' : val('insta'), kanal: checks('kanal'),
        nachricht: val('nachricht'), branche: radio('branche'), branche_text: val('branche_text'), stand: radio('stand'),
        umfang: radio('umfang'), start: radio('start'), kontakt: radio('kontakt'), ziele: checks('ziele'), paket: val('paket'),
        consent: 1, website: val('website'), t: started ? Date.now() - started : 0, src: source(), page: val('page') || 'start'
      };
      const [rec, why] = recommend();
      const btn = $('#lfSubmit');
      const done = (recName, recWhy) => {
        stepsEl.forEach(s => s.hidden = true);
        $('.lf-progress', form).hidden = true;
        $('#lfLabel').textContent = 'Anfrage gesendet';
        $('#lfDoneName').textContent = data.name ? ', ' + data.name.split(' ')[0] : '';
        const r = $('#lfDoneRec'); r.hidden = false; r.querySelector('b').textContent = recName; r.querySelector('span:last-child').textContent = recWhy;
        $('#lfDone').hidden = false; $('#lfDone').focus();
      };

      if (!LIVE) { // ohne Server (z. B. GitHub Pages): E-Mail-Programm öffnen
        const o = (sel) => $(`input[name="${sel}"]:checked + span`, form)?.firstChild?.textContent?.trim() || '–';
        const goals = $$('input[name="ziele"]:checked + span', form).map(s => s.textContent.trim()).join(', ') || '–';
        const body = [`Hallo Victoria,`, ``, data.nachricht || 'ich interessiere mich für Social Media mit reserved.social', ``,
          `Name: ${data.name}`, data.firma && `Unternehmen: ${data.firma}`, `Branche: ${data.branche === 'andere' && data.branche_text ? data.branche_text : o('branche')}`,
          data.ort && `Ort: ${data.ort}`, data.telefon && `Telefon: ${data.telefon}`, data.insta && `${data.kanal.includes('tiktok') && !data.kanal.includes('instagram') ? 'TikTok' : data.kanal.length > 1 ? 'Instagram/TikTok' : 'Instagram'}: ${data.insta}`, data.kanal.includes('none') && 'Noch kein Social-Media-Profil',
          `Ziele: ${goals}`, `Social Media aktuell: ${o('stand')}`, `Umfang: ${o('umfang')}`, `Start: ${o('start')}`,
          `Am liebsten per: ${o('kontakt')}`, `Empfehlung laut Webseite: ${rec}`].filter(Boolean).join('\n');
        const to = $('[data-cms="email"]')?.textContent.trim() || 'reserved.social@web.de';
        location.href = `mailto:${to}?subject=${encodeURIComponent('Anfrage: ' + (data.firma || data.name) + ' – ' + rec)}&body=${encodeURIComponent(body)}`;
        done(rec, why);
        return;
      }
      btn.disabled = true; btn.firstChild.textContent = 'Wird gesendet … ';
      try {
        const res = await fetch(API + '?a=anfrage', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || 'Senden fehlgeschlagen.');
        done(j.rec || rec, j.why || why);
      } catch (err) {
        showErr((err.message || 'Senden fehlgeschlagen.') + ' Du kannst uns auch direkt per E-Mail oder Instagram schreiben.');
        btn.disabled = false; btn.firstChild.textContent = 'Anfrage senden ';
      }
    });
  }

  /* "Anfragen"-Buttons bei den Paketen */
  $$('[data-paket]').forEach(a => a.addEventListener('click', () => {
    const p = a.dataset.paket;
    if (!form) return;
    form.elements.paket.value = p;
    const chip = $('#lfPaket'); chip.textContent = 'Interesse: ' + p; chip.hidden = false;
    const u = { 'Social Media Management': 'all', 'Premium': 'all', '1:1 Coaching': 'coaching', 'Einzelnes Video': 'test' }[p] || 'content';
    const r = form.querySelector(`input[name="umfang"][value="${u}"]`); if (r) r.checked = true;
    updateRec();
  }));

  /* ---------- Handy: feste Leiste unten ---------- */
  const mbar = $('#mbar');
  if (mbar) {
    let pastHero = false, atContact = false;
    const upd = () => {
      const on = pastHero && !atContact && !document.body.classList.contains('menu-open');
      mbar.classList.toggle('show', on);
      mbar.setAttribute('aria-hidden', !on);
      $$('a', mbar).forEach(a => a.tabIndex = on ? 0 : -1);
    };
    document.body.classList.add('has-mbar');
    new IntersectionObserver(([en]) => { pastHero = !en.isIntersecting && en.boundingClientRect.top < 0; upd(); }).observe($('[data-sec="hero"]'));
    new IntersectionObserver(([en]) => { atContact = en.isIntersecting; upd(); }, { rootMargin: '0px 0px -20% 0px' }).observe($('#kontakt'));
    new IntersectionObserver(([en]) => { if (en.isIntersecting) { atContact = true; upd(); } }).observe($('.footer'));
  }

  /* ---------- Impressum / Datenschutz ---------- */
  $$('[data-open]').forEach(b => b.addEventListener('click', () => { $('#' + b.dataset.open).showModal(); root.classList.add('dlg-open'); FX()?.lock(true); }));
  $$('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d || e.target.closest('[data-close]')) d.close(); });
    d.addEventListener('close', () => { if (!$$('dialog[open]').length) { root.classList.remove('dlg-open'); FX()?.lock(false); } });
  });
  if (['#impressum', '#datenschutz'].includes(location.hash)) { $(location.hash).showModal(); root.classList.add('dlg-open'); }

  const y = $('#year'); if (y) y.textContent = new Date().getFullYear();

  /* ---------- Landingpage: Countdown der Aktion ---------- */
  const oc = $('[data-until]');
  if (oc) {
    const end = +oc.dataset.until;
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
      const t = Math.max(0, Math.floor((end - Date.now()) / 1000));
      ['d', 'h', 'm', 's'].forEach((k, i) => { const el = $(`[data-u="${k}"]`, oc); if (el) el.textContent = pad([Math.floor(t / 86400), Math.floor(t % 86400 / 3600), Math.floor(t % 3600 / 60), t % 60][i]); });
      if (t > 0) setTimeout(tick, 1000 - (Date.now() % 1000)); else oc.closest('.offer')?.classList.add('ended');
    };
    tick();
  }

  /* ---------- Statistik ohne Cookies (nur mit Server) ---------- */
  function track(id) { if (T) T.c.add(id); }
  var T = null;
  const dnt = navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true;
  if (LIVE && !dnt && navigator.sendBeacon) {
    T = { s: new Set(), c: new Set(), sentS: new Set(), sentC: new Set(), vis: Date.now(), acc: 0, engaged: false, sentE: false };
    const secIo = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { T.s.add(en.target.dataset.sec); secIo.unobserve(en.target); } }), { threshold: 0.35 });
    $$('[data-sec]').forEach(s => secIo.observe(s));
    document.addEventListener('click', e => { const t = e.target.closest('[data-track]'); if (t) T.c.add(t.dataset.track); }, true);
    const flush = () => {
      const now = Date.now();
      if (T.vis) { T.acc += (now - T.vis) / 1000; T.vis = 0; }
      if (T.acc >= 15) T.engaged = true;
      const s = [...T.s].filter(x => !T.sentS.has(x)), c = [...T.c].filter(x => !T.sentC.has(x));
      const d = Math.round(T.acc); T.acc -= d;
      const e = T.engaged && !T.sentE;
      if (!s.length && !c.length && !d && !e) return;
      const ok = navigator.sendBeacon(API + '?a=t', JSON.stringify({ s, c, d, e: e ? 1 : 0 }));
      if (ok) { s.forEach(x => T.sentS.add(x)); c.forEach(x => T.sentC.add(x)); if (e) T.sentE = true; }
    };
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); else T.vis = Date.now(); });
    addEventListener('pagehide', flush);
    setTimeout(() => { if (document.visibilityState === 'visible') { T.acc += (Date.now() - T.vis) / 1000; T.vis = Date.now(); if (T.acc >= 15) { T.engaged = true; flush(); } } }, 16000);
  }
})();
