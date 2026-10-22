/* Reserved. Admin – kleine Helfer */
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
  // Meldungen nach ein paar Sekunden ausblenden
  document.querySelectorAll('.flash[role="status"]').forEach(f => setTimeout(() => { f.style.transition = 'opacity .5s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 500); }, 5000));
})();
