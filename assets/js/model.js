/* reserved.social – Model-Portfolio: Großansicht der Fotos
   Antippen = groß · Pfeile/Tasten/Wischen = weiter · Esc oder daneben tippen = zu */
(() => {
  'use strict';
  const lb = document.getElementById('mdLb');
  const items = [...document.querySelectorAll('.md-ph button')];
  if (!lb || !items.length) return;
  const img = lb.querySelector('img'), num = lb.querySelector('.md-lb-n'), root = document.documentElement;
  let i = 0;
  const show = (n) => {
    i = (n + items.length) % items.length;
    const src = items[i].querySelector('img');
    img.src = src.currentSrc || src.src; img.alt = src.alt;
    num.textContent = (i + 1) + ' / ' + items.length;
    [-1, 1].forEach((d) => { const p = items[(i + d + items.length) % items.length].querySelector('img'); if (p) new Image().src = p.src; });
  };
  items.forEach((b, n) => b.addEventListener('click', () => {
    show(n);
    if (!lb.open) { lb.showModal(); root.classList.add('dlg-open'); window.RsFX?.lock?.(true); }
  }));
  lb.querySelector('.prev').addEventListener('click', (e) => { e.stopPropagation(); show(i - 1); });
  lb.querySelector('.next').addEventListener('click', (e) => { e.stopPropagation(); show(i + 1); });
  img.addEventListener('click', (e) => { e.stopPropagation(); show(i + 1); });
  lb.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); show(i + 1); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); show(i - 1); }
  });
  let x0 = null, y0 = 0;
  lb.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
  lb.addEventListener('touchend', (e) => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
    if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) show(i + (dx < 0 ? 1 : -1));
    else if (dy > 90 && Math.abs(dy) > Math.abs(dx)) lb.close();
  });
})();
