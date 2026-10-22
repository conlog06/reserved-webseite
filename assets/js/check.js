/* Reserved. – Fit-Check „Zusammenarbeit starten“ (/start/)
   Eine Frage pro Bildschirm: Antippen oder Buchstabe (A, B, C …) wählt aus,
   Enter geht weiter, Esc/Zurück geht zurück. Läuft auch ohne GSAP. */
(() => {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const root = document.documentElement;
  const LIVE = root.hasAttribute('data-cms-live');
  const API = root.dataset.api || '../_reserved/api.php';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const gsap = !reduce && window.gsap ? window.gsap : null;
  const form = $('#fc');
  const steps = $$('.fc-step', form);
  const LAST = steps.length - 2;            // letzter Frageschritt (Kontakt)
  let cur = 0, busy = false;
  // Fragen fortlaufend nummerieren (falls die Budget-Frage ausgeschaltet ist)
  steps.forEach((s, i) => { const n = $('.fc-num', s); if (n) n.firstChild.textContent = i; });

  // Profilbild aus dem Admin
  const av = $('[data-cms-img="avatar"]');
  if (av && av.getAttribute('src')) { av.hidden = false; av.previousElementSibling.hidden = true; }

  // Herkunft (wie auf der Startseite)
  let src = 'direkt';
  try {
    src = sessionStorage.getItem('rs-src') || '';
    if (!src) {
      const u = new URLSearchParams(location.search);
      src = u.get('utm_source') || '';
      if (!src && document.referrer) { try { const h = new URL(document.referrer).hostname; if (h !== location.hostname) src = h.replace(/^www\./, ''); } catch (e) {} }
      if (!src && /Instagram/i.test(navigator.userAgent)) src = 'Instagram';
      src = src || 'direkt'; sessionStorage.setItem('rs-src', src);
    }
  } catch (e) {}

  /* ---------- Statistik: erreichte Schritte ---------- */
  const dnt = navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true;
  const reached = new Set(), sent = new Set();
  const flush = () => {
    if (!LIVE || dnt || !navigator.sendBeacon) return;
    const c = [...reached].filter(x => !sent.has(x));
    if (!c.length) return;
    if (navigator.sendBeacon(API + '?a=t', JSON.stringify({ c, s: ['hero'], d: 0 }))) c.forEach(x => sent.add(x));
  };
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
  addEventListener('pagehide', flush);

  /* ---------- Werte ---------- */
  const val = (n) => (form.elements[n]?.value || '').trim();
  const radio = (n) => $(`input[name="${n}"]:checked`, form)?.value || '';
  const checks = (n) => $$(`input[name="${n}"]:checked`, form).map(i => i.value);
  const label = (n) => $(`input[name="${n}"]:checked + span b`, form)?.textContent || '–';
  const stand = () => ({ never: 'none', rare: 'rare', weekly: 'regular', daily: 'regular' }[radio('posting')] || '');
  // gleiche Empfehlung wie _reserved/leads.php
  const recommend = () => {
    const g = checks('ziele'), u = radio('umfang');
    if (u === 'test') return ['Einzelnes Video', 'Zum Reinschnuppern ohne laufende Betreuung – du siehst, wie Reserved. arbeitet.'];
    if (u === 'all') return ['Premium', 'Wir übernehmen alles: Strategie, Content, Posten und Community – du musst dich um nichts kümmern.'];
    if (stand() === 'regular' || g.length >= 3 || g.some(x => ['events', 'anfragen', 'personal'].includes(x)))
      return ['Standard', `Genug Content für regelmäßige Präsenz inklusive Stories – ideal, um ${g.length > 1 ? 'mehrere Ziele' : 'dein Ziel'} gezielt anzugehen.`];
    return ['Starter', 'Ein guter Start: 4 Videos und 4 Fotos im Monat bringen Regelmäßigkeit in deinen Auftritt.'];
  };

  /* ---------- Schritte ---------- */
  const progress = () => {
    const p = Math.round(Math.min(cur, LAST + 1) / (LAST + 1) * 100);
    $('#fcBar').style.width = p + '%';
    $('#fcPct').textContent = p + ' %';
    $('#fcBack').hidden = cur === 0 || cur > LAST;
  };
  const focusFirst = (s) => {
    const el = $('input.fc-in, textarea.fc-in', s);
    if (el && matchMedia('(hover: hover)').matches) setTimeout(() => el.focus({ preventScroll: true }), gsap ? 380 : 50);
  };
  const show = (n, dir = 1) => {
    if (n === cur || busy || n < 0 || n >= steps.length) return;
    const from = steps[cur], to = steps[n];
    busy = true;
    const done = () => { from.hidden = true; busy = false; };
    to.hidden = false; cur = n; progress();
    reached.add('check-' + (n + 1));
    if (gsap) {
      gsap.fromTo(from, { y: 0, autoAlpha: 1 }, { y: -40 * dir, autoAlpha: 0, duration: 0.35, ease: 'power2.in', onComplete: done });
      gsap.fromTo(to, { y: 50 * dir, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.6, ease: 'power3.out', delay: 0.25 });
      gsap.from($$('.fc-opt', to), { y: 16, autoAlpha: 0, duration: 0.45, stagger: 0.04, ease: 'power2.out', delay: 0.4, clearProps: 'transform' });
    } else done();
    focusFirst(to);
    scrollTo(0, 0);
  };
  const next = () => {
    if (cur === LAST) return submit();
    if (cur === 3 && radio('branche') === 'andere' && !val('branche_text')) { $('.fc-other input').focus(); return; }
    show(cur + 1, 1);
  };
  const back = () => show(cur - 1, -1);

  $$('.fc-next').forEach(b => b.addEventListener('click', next));
  $$('.fc-skip').forEach(b => b.addEventListener('click', () => show(cur + 1, 1)));
  $('#fcBack').addEventListener('click', back);

  // Antworten: bei Einzelauswahl automatisch weiter
  form.addEventListener('change', (e) => {
    const t = e.target;
    if (t.name === 'branche') {
      const other = $('.fc-other'); other.hidden = t.value !== 'andere';
      if (t.value === 'andere') { $('input', other).focus(); return; }
    }
    if (t.type === 'radio' && t.closest('[data-auto]')) setTimeout(() => show(cur + 1, 1), gsap ? 280 : 120);
  });
  $('input[name="ort"]', form)?.addEventListener('input', (e) => {
    const v = e.target.value.trim().toLowerCase();
    $('.fc-far').hidden = !(v.length > 2 && !v.includes('osnabr') && !/^490\d\d/.test(v));
  });

  // Tastatur: Enter = weiter, Buchstaben = Antwort, Esc = zurück
  document.addEventListener('keydown', (e) => {
    if ($('dialog[open]')) return;
    const step = steps[cur];
    const typing = e.target.matches('input.fc-in, textarea');
    if (e.key === 'Enter' && !(e.target.tagName === 'TEXTAREA' && !e.metaKey && !e.ctrlKey)) {
      if (e.target.matches('button, a')) return;
      e.preventDefault(); next(); return;
    }
    if (e.key === 'Escape' && !typing) { back(); return; }
    if (!typing && /^[a-zA-Z]$/.test(e.key) && !e.metaKey && !e.ctrlKey && !e.altKey) {
      const opt = $$('.fc-opt input', step)[e.key.toUpperCase().charCodeAt(0) - 65];
      if (opt) { opt.click(); }
    }
  });

  /* ---------- Absenden ---------- */
  const err = (m) => { const el = $('.fc-error', form); el.textContent = m; el.hidden = !m; };
  let started = Date.now();
  async function submit() {
    err('');
    const name = form.elements.name, mail = form.elements.email;
    if (!val('name')) { name.focus(); return err('Wie darf ich dich nennen?'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email'))) { mail.focus(); return err('Bitte gib eine gültige E-Mail-Adresse an.'); }
    if (!form.elements.consent.checked) return err('Bitte bestätige noch kurz den Hinweis zum Datenschutz.');
    const data = {
      name: val('name'), email: val('email'), telefon: val('telefon'), firma: val('firma'), ort: val('ort'), insta: val('insta').replace(/^neu$/i, ''),
      nachricht: val('nachricht'), branche: radio('branche'), branche_text: val('branche_text'), ziele: checks('ziele'),
      follower: radio('follower'), posting: radio('posting'), budget: radio('budget'), stand: stand(), umfang: radio('umfang'), start: radio('start'),
      kontakt: radio('kontakt') || 'mail', consent: 1, website: val('website'), t: Date.now() - started, src, page: 'check'
    };
    let [rec, why] = recommend();
    const btn = $('.fc-next', steps[cur]);
    if (!LIVE) {
      const body = ['Hallo Victoria,', '', data.nachricht || 'ich habe den Fit-Check auf der Webseite gemacht.', '',
        `Name: ${data.name}`, data.firma && `Unternehmen: ${data.firma}`, data.ort && `Ort: ${data.ort}`, data.insta && `Instagram: ${data.insta}`,
        `Branche: ${data.branche === 'andere' && data.branche_text ? data.branche_text : label('branche')}`,
        `Ziele: ${$$('input[name="ziele"]:checked + span b', form).map(b => b.textContent).join(', ') || '–'}`,
        `Follower: ${label('follower')}`, `Postet aktuell: ${label('posting')}`, radio('budget') && `Budget: ${radio('budget')}`, `Umfang: ${label('umfang')}`, `Start: ${label('start')}`,
        data.telefon && `Telefon: ${data.telefon}`, `Am liebsten per: ${label('kontakt')}`, `Empfehlung laut Fit-Check: ${rec}`].filter(Boolean).join('\n');
      location.href = `mailto:reserved.social@web.de?subject=${encodeURIComponent('Fit-Check: ' + (data.firma || data.name))}&body=${encodeURIComponent(body)}`;
    } else {
      btn.disabled = true; btn.firstChild.textContent = 'Wird gesendet … ';
      try {
        const res = await fetch(API + '?a=anfrage', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const j = await res.json().catch(() => ({}));
        if (!res.ok || !j.ok) throw new Error(j.error || 'Senden fehlgeschlagen.');
        rec = j.rec || rec; why = j.why || why;
      } catch (e) {
        btn.disabled = false; btn.firstChild.textContent = 'Absenden ';
        return err((e.message || 'Senden fehlgeschlagen.') + ' Du kannst mir auch direkt per Instagram oder E-Mail schreiben.');
      }
    }
    if (data.name) {
      const t = $('[data-cms-fc="done_title"]');
      t.textContent = t.textContent.trim().replace(/[!.]+$/, '') + ', ' + data.name.split(' ')[0] + '!';
    }
    const r = $('#fcRec'); r.hidden = false; $('b', r).textContent = rec; $('small', r).textContent = why;
    show(LAST + 1, 1); flush();
  }

  /* ---------- Impressum / Datenschutz ---------- */
  $$('[data-open]').forEach(b => b.addEventListener('click', () => $('#' + b.dataset.open).showModal()));
  $$('dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d || e.target.closest('[data-close]')) d.close(); }));

  /* ---------- Start ---------- */
  steps.forEach((s, i) => { s.hidden = i !== 0; });
  progress(); reached.add('check-1');
  if (gsap) gsap.from($$('.fc-intro > *'), { y: 30, autoAlpha: 0, duration: 0.9, stagger: 0.1, ease: 'power3.out', delay: 0.1, clearProps: 'transform' });
})();
