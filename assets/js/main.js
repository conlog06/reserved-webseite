(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Navigation: Hintergrund beim Scrollen */
  const nav = $('#nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 30);
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Mobile-Menü */
  const burger = $('#burger');
  const toggleMenu = (open) => {
    document.body.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', open);
    burger.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    document.body.style.overflow = open ? 'hidden' : '';
  };
  burger.addEventListener('click', () => toggleMenu(!document.body.classList.contains('menu-open')));
  $$('#mobile-menu a').forEach(a => a.addEventListener('click', () => toggleMenu(false)));
  addEventListener('keydown', e => { if (e.key === 'Escape') toggleMenu(false); });

  /* Scroll-Reveal */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  $$('.reveal').forEach(el => io.observe(el));

  /* Handy-Mockup: Story-Slider */
  const slides = $$('#igSlides img');
  const bars = $('#igBars');
  const DUR = 3200;
  slides.forEach(() => bars.appendChild(document.createElement('i')));
  const barEls = $$('i', bars);
  let cur = 0, timer;
  const show = (n) => {
    cur = (n + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle('on', i === cur));
    barEls.forEach((b, i) => {
      b.classList.remove('active');
      b.classList.toggle('done', i < cur);
    });
    void barEls[cur].offsetWidth;
    if (!reduce) barEls[cur].classList.add('active');
    clearTimeout(timer);
    timer = setTimeout(() => show(cur + 1), DUR);
  };
  bars.style.setProperty('--dur', DUR + 'ms');
  $('#igSlides').addEventListener('click', e => {
    const r = e.currentTarget.getBoundingClientRect();
    show(e.clientX - r.left < r.width / 3 ? cur - 1 : cur + 1);
  });
  // Bilder vorladen, damit der Wechsel flüssig ist
  slides.forEach(s => s.removeAttribute('loading'));
  show(0);

  /* Instagram-Feed: Pfeile, Drag & Fortschritt */
  const track = $('#feedTrack');
  const bar = $('#feedBar');
  const step = () => track.querySelector('.post').offsetWidth + 18;
  $('#feedPrev').addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
  $('#feedNext').addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
  const updateBar = () => {
    const max = track.scrollWidth - track.clientWidth;
    const vis = track.clientWidth / track.scrollWidth;
    bar.style.width = (vis * 100) + '%';
    bar.style.transform = `translateX(${max > 0 ? (track.scrollLeft / max) * ((1 - vis) / vis) * 100 : 0}%)`;
  };
  track.addEventListener('scroll', updateBar, { passive: true });
  addEventListener('resize', updateBar);
  updateBar();

  let down = false, startX = 0, startLeft = 0, moved = false;
  track.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'mouse') return;
    down = true; moved = false; startX = e.clientX; startLeft = track.scrollLeft;
    track.classList.add('dragging');
  });
  addEventListener('pointermove', e => {
    if (!down) return;
    const dx = e.clientX - startX;
    if (Math.abs(dx) > 4) moved = true;
    track.scrollLeft = startLeft - dx;
  });
  addEventListener('pointerup', () => {
    if (!down) return;
    down = false; track.classList.remove('dragging');
  });
  track.addEventListener('click', e => { if (moved) e.preventDefault(); }, true);

  /* Feedback-Filter */
  $$('.chip').forEach(chip => chip.addEventListener('click', () => {
    $$('.chip').forEach(c => c.setAttribute('aria-pressed', c === chip));
    const f = chip.dataset.filter;
    if (f !== 'all') wall.classList.add('expanded');
    $$('#wall .quote').forEach(q => {
      const hide = f !== 'all' && q.dataset.client !== f;
      q.classList.toggle('hide', hide);
      if (!hide) q.classList.add('in');
    });
  }));

  const wall = $('#wall');
  $('#moreBtn').addEventListener('click', () => wall.classList.add('expanded'));

  /* Pakete: Umschalter (mobil) */
  const plans = $('#plans');
  $$('.plan-switch button').forEach(b => b.addEventListener('click', () => {
    $$('.plan-switch button').forEach(x => x.setAttribute('aria-selected', x === b));
    plans.dataset.show = b.dataset.plan;
  }));

  /* "Anfragen"-Buttons wählen das Paket im Formular vor */
  $$('[data-paket]').forEach(a => a.addEventListener('click', () => {
    $('#f-paket').value = a.dataset.paket;
  }));

  /* Formular → vorausgefüllte E-Mail */
  $('#form').addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const name = (fd.get('name') || '').trim();
    if (!name) { $('#f-name').focus(); $('#f-name').style.borderColor = '#D98A73'; return; }
    const firma = (fd.get('firma') || '').trim();
    const subject = `Anfrage: ${fd.get('paket')}${firma ? ' – ' + firma : ''}`;
    const body = [
      'Hallo Victoria,', '',
      (fd.get('msg') || '').trim() || 'ich interessiere mich für Social Media mit Reserved.', '',
      `Name: ${name}`,
      firma ? `Unternehmen: ${firma}` : null,
      fd.get('branche') ? `Branche: ${fd.get('branche')}` : null,
      `Paket: ${fd.get('paket')}`
    ].filter(v => v !== null).join('\n');
    location.href = `mailto:reserved.social@web.de?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  });

  /* Impressum / Datenschutz */
  $$('[data-open]').forEach(b => b.addEventListener('click', () => $('#' + b.dataset.open).showModal()));
  $$('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d || e.target.closest('[data-close]')) d.close(); });
  });
  if (location.hash === '#impressum') $('#impressum').showModal();

  $('#year').textContent = new Date().getFullYear();
})();
