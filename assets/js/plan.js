/* reserved.social – Download „Content-Plan Starter“ (/plan/)
   Name + E-Mail → Bestätigungsmail (Double-Opt-in) → Danke-Seite mit Download */
(() => {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const form = $('#planForm');
  if (!form) return;
  const root = document.documentElement;
  const LIVE = root.hasAttribute('data-cms-live');
  const API = root.dataset.api || '../_reserved/api.php';
  const err = (m) => { const el = $('#pfError'); el.textContent = m; el.hidden = !m; };
  let src = 'direkt';
  try {
    src = sessionStorage.getItem('rs-src') || new URLSearchParams(location.search).get('utm_source') || (/Instagram/i.test(navigator.userAgent) ? 'Instagram' : '') || 'direkt';
    sessionStorage.setItem('rs-src', src);
  } catch (e) {}

  form.addEventListener('submit', async (e) => {
    e.preventDefault(); err('');
    const name = form.elements.name, mail = form.elements.email, ok = form.elements.consent;
    if (!name.value.trim()) { name.focus(); return err('Wie heißt du?'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail.value.trim())) { mail.focus(); return err('Bitte gib eine gültige E-Mail-Adresse an.'); }
    if (!ok.checked) return err('Bitte bestätige noch kurz die Einwilligung.');
    if (!LIVE) return err('Der Download ist auf dieser Vorschau noch nicht aktiv – schreib uns gern per Instagram-DM, dann schicken wir dir den Plan.');
    const btn = $('#pfSubmit'); btn.disabled = true;
    try {
      const res = await fetch(API + '?a=plan', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name.value.trim(), email: mail.value.trim(), consent: 1, website: form.elements.website.value, src }) });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) throw new Error(j.error || 'Das hat leider nicht geklappt.');
      $('#pfMail').textContent = mail.value.trim();
      $('.pf-in', form).hidden = true;
      const d = $('#pfDone'); d.hidden = false; d.focus();
    } catch (x) {
      btn.disabled = false;
      err((x.message || 'Das hat leider nicht geklappt.') + ' Versuch es bitte noch einmal.');
    }
  });
})();
