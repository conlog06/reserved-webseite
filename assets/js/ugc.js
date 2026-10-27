/* reserved.social – UGC-Seite: Beispielvideos per Klick abspielen (immer nur eins gleichzeitig) */
(() => {
  'use strict';
  const vids = [...document.querySelectorAll('.ug-vid')];
  if (!vids.length) return;
  vids.forEach((fig) => {
    const v = fig.querySelector('video'), btn = fig.querySelector('.ug-play');
    const play = () => {
      vids.forEach((o) => { const ov = o.querySelector('video'); if (ov !== v && !ov.paused) ov.pause(); });
      v.controls = true; v.play().catch(() => {});
    };
    btn.addEventListener('click', play);
    v.addEventListener('play', () => fig.classList.add('playing'));
    v.addEventListener('pause', () => fig.classList.remove('playing'));
    v.addEventListener('ended', () => { fig.classList.remove('playing'); v.controls = false; });
  });
})();
