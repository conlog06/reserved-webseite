/* reserved.social – UGC-Seite: Beispielvideos
   Drüberfahren = stumme Vorschau · Klick = mit Ton und Steuerung (immer nur eins gleichzeitig) */
(() => {
  'use strict';
  const vids = [...document.querySelectorAll('.ug-vid')];
  if (!vids.length) return;
  const hover = matchMedia('(hover: hover)').matches && !matchMedia('(prefers-reduced-motion: reduce)').matches;
  vids.forEach((fig) => {
    const v = fig.querySelector('video'), btn = fig.querySelector('.ug-play');
    let full = false;
    const play = () => {
      vids.forEach((o) => { const ov = o.querySelector('video'); if (ov !== v && !ov.paused) ov.pause(); });
      full = true; v.muted = false; v.loop = false; v.controls = true; v.play().catch(() => {});
      fig.classList.add('playing');
    };
    btn.addEventListener('click', play);
    if (hover) {
      fig.addEventListener('mouseenter', () => { if (full) return; v.muted = true; v.loop = true; v.preload = 'auto'; v.play().then(() => fig.classList.add('preview')).catch(() => {}); });
      fig.addEventListener('mouseleave', () => { if (full) return; v.pause(); fig.classList.remove('preview'); });
    }
    v.addEventListener('pause', () => { if (full) fig.classList.remove('playing'); });
    v.addEventListener('ended', () => { full = false; fig.classList.remove('playing'); v.controls = false; v.currentTime = 0; });
  });
})();

/* Anfrage-Formular im E-Mail-Look */
(() => {
  'use strict';
  const form = document.getElementById('ugForm');
  if (!form) return;
  const root = document.documentElement, LIVE = root.hasAttribute('data-cms-live'), API = root.dataset.api || '../_reserved/api.php';
  const err = (m) => { const el = document.getElementById('ugError'); el.textContent = m; el.hidden = !m; };
  const subj = form.elements.betreff;
  form.querySelectorAll('.mf-chips button').forEach((b) => b.addEventListener('click', () => {
    const on = b.getAttribute('aria-pressed') !== 'true';
    form.querySelectorAll('.mf-chips button').forEach((x) => x.setAttribute('aria-pressed', 'false'));
    b.setAttribute('aria-pressed', String(on));
    if (on && (!subj.value.trim() || /^Anfrage: /.test(subj.value))) subj.value = 'Anfrage: ' + b.dataset.subj;
    form.elements.nachricht.focus();
  }));
  form.addEventListener('submit', async (e) => {
    e.preventDefault(); err('');
    const f = form.elements, mail = f.email.value.trim();
    if (!f.name.value.trim()) { f.name.focus(); return err('Wie heißt du?'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) { f.email.focus(); return err('Bitte gib eine gültige E-Mail-Adresse an.'); }
    if (f.nachricht.value.trim().length < 10) { f.nachricht.focus(); return err('Schreib Victoria noch kurz, worum es geht.'); }
    if (!f.consent.checked) return err('Bitte bestätige noch kurz den Datenschutz-Hinweis.');
    if (!LIVE) {
      const to = (document.querySelector('[data-ug-href="mail"]')?.getAttribute('href') || 'mailto:booking.vfranz@web.de').replace(/^mailto:/, '');
      location.href = `mailto:${to}?subject=${encodeURIComponent(f.betreff.value || 'Anfrage')}&body=${encodeURIComponent(f.nachricht.value + '\n\n' + f.name.value + (f.firma.value ? ' · ' + f.firma.value : ''))}`;
      return;
    }
    const btn = document.getElementById('ugSend'); btn.disabled = true;
    try {
      const res = await fetch(API + '?a=ugc', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
        name: f.name.value.trim(), email: mail, firma: f.firma.value.trim(), betreff: f.betreff.value.trim(), nachricht: f.nachricht.value.trim(), consent: 1, website: f.website.value,
        src: (() => { try { return sessionStorage.getItem('rs-src') || new URLSearchParams(location.search).get('utm_source') || 'direkt'; } catch (x) { return 'direkt'; } })() }) });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) throw new Error(j.error || 'Das hat leider nicht geklappt.');
      form.querySelector('.mf-in').hidden = true;
      document.getElementById('ugDoneMail').textContent = mail;
      const d = document.getElementById('ugDone'); d.hidden = false; d.focus();
    } catch (x) { btn.disabled = false; err(x.message + ' Versuch es bitte noch einmal.'); }
  });
})();
