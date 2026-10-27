/* reserved.social Admin – kleine Helfer */
(() => {
  // Listen: Zeilen hinzufügen / löschen
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    if (add) {
      const tpl = document.getElementById(add.dataset.add);
      const to = document.getElementById(add.dataset.to);
      const html = tpl.innerHTML.replaceAll('__', 'n' + Date.now());
      const box = document.createElement('div'); box.innerHTML = html.trim();
      const el = box.firstElementChild;
      add.hasAttribute('data-prepend') ? to.prepend(el) : to.append(el);
      el.querySelector('input,textarea')?.focus();
      dirty = true;
    }
    const rm = e.target.closest('[data-remove]');
    if (rm) {
      const row = rm.closest('.pt-row,.lrow,.qed');
      const txt = row.querySelector('input,textarea')?.value;
      if (!txt || confirm('Wirklich entfernen?')) { row.remove(); dirty = true; }
    }
  });
  // Warnen, wenn ungespeicherte Änderungen verloren gehen
  let dirty = false;
  document.querySelectorAll('form.form').forEach(f => {
    f.addEventListener('input', () => { dirty = true; });
    f.addEventListener('submit', () => { dirty = false; });
  });
  addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  // Links kopieren
  const copy = (b, t) => {
    const done = () => { const o = b.textContent; b.textContent = 'Kopiert ✓'; setTimeout(() => { b.textContent = o; }, 1600); };
    navigator.clipboard ? navigator.clipboard.writeText(t).then(done, () => prompt('Link kopieren:', t)) : prompt('Link kopieren:', t);
  };
  document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => copy(b, b.dataset.copy)));
  const uc = document.getElementById('utmCopy');
  if (uc) uc.addEventListener('click', () => {
    const base = uc.closest('.utm').dataset.base;
    const camp = (document.getElementById('utmCamp').value || 'kampagne').trim().toLowerCase().replace(/[^a-z0-9-]+/g, '-');
    copy(uc, base + '?utm_source=kampagne&utm_campaign=' + encodeURIComponent(camp));
  });
  // Meldungen nach ein paar Sekunden ausblenden
  document.querySelectorAll('.flash[role="status"]').forEach(f => setTimeout(() => { f.style.transition = 'opacity .5s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 500); }, 5000));
})();
