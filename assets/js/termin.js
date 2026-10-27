/* reserved.social – Termin bestätigen (/termin/?t=…)
   „Ja, passt mir“ / „Passt leider nicht“ (mit Nachricht) → Victoria bekommt eine Mail */
(() => {
  'use strict';
  const box = document.querySelector('.bk-prop');
  if (!box) return;
  const root = document.documentElement;
  const API = root.dataset.api || '../_reserved/api.php';
  const err = (m) => { const el = document.getElementById('bkError'); el.textContent = m; el.hidden = !m; };
  box.addEventListener('click', async (e) => {
    const show = e.target.closest('[data-show-no]');
    if (show) { const no = box.querySelector('.bk-no'); no.hidden = false; no.querySelector('textarea').focus(); show.hidden = true; return; }
    const btn = e.target.closest('[data-answer]');
    if (!btn) return;
    err(''); btn.disabled = true;
    try {
      const res = await fetch(API + '?a=termin_antwort', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ t: box.dataset.t, antwort: btn.dataset.answer, nachricht: box.querySelector('.bk-no textarea')?.value || '' }) });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) throw new Error(j.error || 'Das hat leider nicht geklappt.');
      location.reload();
    } catch (x) { btn.disabled = false; err(x.message + ' Versuch es bitte noch einmal oder antworte einfach auf die E-Mail.'); }
  });
})();
