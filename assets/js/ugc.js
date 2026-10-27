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
