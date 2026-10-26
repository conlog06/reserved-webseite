/* ==========================================================================
   Reserved. – effects.js
   Animationen & Scroll-Verhalten mit GSAP (ScrollTrigger, SplitText) und Lenis.

   Vanilla-JS-Nachbauten von Komponenten aus React Bits:
   ScrollFloat, ScrollReveal, RotatingText, CountUp, ScrollVelocity, Magnet,
   SpotlightCard, ClickSpark, TiltedCard
   React Bits – Copyright (c) David Haz – MIT + Commons Clause
   https://github.com/DavidHDev/react-bits

   Performance-Regeln:
   - nur transform & opacity (plus einmalige clip-path-Einblendungen)
   - kein Pinning, ein gemeinsamer Takt (gsap.ticker) für Lenis & ScrollTrigger
   - Maus-Effekte nur mit echter Maus, am Handy bleibt natives Scrollen
   - "Bewegung reduzieren" im System → alles aus (main.js übernimmt einfache Einblendungen)
   ========================================================================== */
(function () {
  'use strict';
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (reduce || typeof window.gsap === 'undefined' || typeof window.ScrollTrigger === 'undefined') return;

  var gsap = window.gsap, ST = window.ScrollTrigger, Split = window.SplitText;
  gsap.registerPlugin(ST);
  if (Split) gsap.registerPlugin(Split);
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var root = document.documentElement;
  root.classList.add('fx');

  var FX = window.RsFX = { lenis: null, refresh: function () { ST.refresh(); } };
  var navH = function () { return ($('#nav') ? $('#nav').offsetHeight : 70) + 8; };

  /* ---------- Lenis: weiches Scrollen (Mausrad/Trackpad – Touch bleibt nativ) ---------- */
  if (typeof window.Lenis !== 'undefined') {
    var lenis = new window.Lenis({ autoRaf: false, lerp: 0.1, smoothWheel: true, wheelMultiplier: 1 });
    FX.lenis = lenis;
    gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    gsap.ticker.lagSmoothing(0);
    lenis.on('scroll', ST.update);
  }
  FX.scrollTo = function (target) {
    var el = typeof target === 'string' ? $(target) : target;
    if (!el) return;
    if (FX.lenis) FX.lenis.scrollTo(el, { offset: parseFloat(getComputedStyle(el).scrollMarginTop) || 0, duration: 1.4, easing: function (t) { return 1 - Math.pow(1 - t, 4); } });
    else el.scrollIntoView({ behavior: 'smooth' });
  };
  FX.lock = function (on) { if (FX.lenis) on ? FX.lenis.stop() : FX.lenis.start(); };
  // Anker-Links sanft anfahren
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#"]');
    if (!a || a.closest('#mobile-menu')) return;
    var id = a.getAttribute('href');
    if (id.length < 2 || !$(id)) return;
    e.preventDefault();
    FX.scrollTo(id);
    history.replaceState(null, '', id);
  });

  /* ---------- Mobiles Menü ---------- */
  FX.menu = function (open) {
    var links = $$('.mm-links a'), rest = $$('.mm-kicker, .mm-cta, .mm-foot');
    gsap.killTweensOf(links.concat(rest));
    if (open) {
      gsap.fromTo(links, { yPercent: 110, rotate: 4, autoAlpha: 0 }, { yPercent: 0, rotate: 0, autoAlpha: 1, duration: 0.9, stagger: 0.07, ease: 'power4.out', delay: 0.22 });
      gsap.fromTo(rest, { y: 24, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.8, stagger: 0.08, ease: 'power3.out', delay: 0.45 });
    } else {
      gsap.to(links.concat(rest), { autoAlpha: 0, y: -10, duration: 0.3, stagger: 0.02, ease: 'power2.in' });
    }
  };

  /* ---------- Magnet (React Bits) ---------- */
  if (fine) $$('.btn-primary, .nav-cta, .round, .mbar-dm, .way .ic').forEach(function (el) {
    var x = gsap.quickTo(el, 'x', { duration: 0.4, ease: 'power3' }), y = gsap.quickTo(el, 'y', { duration: 0.4, ease: 'power3' });
    el.addEventListener('pointermove', function (e) { var r = el.getBoundingClientRect(); x((e.clientX - r.left - r.width / 2) / 4); y((e.clientY - r.top - r.height / 2) / 4); });
    el.addEventListener('pointerleave', function () { gsap.to(el, { x: 0, y: 0, duration: 0.7, ease: 'elastic.out(1, .45)' }); });
  });

  /* ---------- SpotlightCard (React Bits) ---------- */
  if (fine) {
    var spotSel = '.service, .svc-card, .fact, .quote, .card, .result, .pcard, .lead-form, .ig-profile, .way, .offer, .why-item, .extra';
    $$(spotSel).forEach(function (c) { c.classList.add('spot'); });
    document.addEventListener('pointermove', function (e) {
      var c = e.target.closest && e.target.closest('.spot'); if (!c) return;
      var r = c.getBoundingClientRect();
      c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
    }, { passive: true });
  }

  /* ---------- ClickSpark (React Bits) ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.btn-primary, .mm-cta'); if (!b || !e.clientX) return;
    for (var i = 0; i < 8; i++) {
      var s = document.createElement('i'), a = i / 8 * Math.PI * 2;
      s.className = 'spark'; s.style.left = e.clientX + 'px'; s.style.top = e.clientY + 'px';
      document.body.appendChild(s);
      gsap.fromTo(s, { rotation: a * 180 / Math.PI, x: 0, y: 0, autoAlpha: 1, scaleY: 1 },
        { x: Math.sin(a) * 28, y: -Math.cos(a) * 28, scaleY: 0.2, autoAlpha: 0, duration: 0.5, ease: 'power2.out', onComplete: s.remove.bind(s) });
    }
  });

  /* ---------- TiltedCard (React Bits) – Instagram-Kacheln ---------- */
  if (fine) $$('.tile, .ptile').forEach(function (t) {
    var rx = gsap.quickTo(t, 'rotationX', { duration: 0.5, ease: 'power3' }), ry = gsap.quickTo(t, 'rotationY', { duration: 0.5, ease: 'power3' });
    gsap.set(t, { transformPerspective: 700 });
    t.addEventListener('pointermove', function (e) { var r = t.getBoundingClientRect(); ry(((e.clientX - r.left) / r.width - 0.5) * 12); rx(-((e.clientY - r.top) / r.height - 0.5) * 12); });
    t.addEventListener('pointerleave', function () { rx(0); ry(0); });
  });

  /* ---------- CountUp (React Bits) ---------- */
  function countUp(el, delay) {
    var m = (el.textContent || '').trim().match(/^(\d+(?:[.,]\d+)?)(.*)$/);
    if (!m) return;
    var to = parseFloat(m[1].replace('.', '').replace(',', '.')), suffix = m[2], o = { v: 0 };
    el.textContent = '0' + suffix;
    gsap.to(o, { v: to, duration: 1.8, delay: delay || 0, ease: 'power2.out', onUpdate: function () { el.textContent = Math.round(o.v).toLocaleString('de-DE') + suffix; } });
  }

  /* ---------- Hero-Einstieg ---------- */
  var hero = $('.hero, .lp-hero');
  // nur was beim Laden sichtbar ist, gehört zum Einstieg – der Rest blendet beim Scrollen ein
  var heroReveals = hero ? $$('.reveal', hero).filter(function (el) { return el.getBoundingClientRect().top < innerHeight; }) : [];
  var intro = gsap.timeline({ defaults: { ease: 'power3.out' }, delay: 0.1 });
  if ($('#nav')) intro.from('#nav', { y: -20, duration: 0.7 }, 0);
  if (hero) {
    var h1 = $('h1', hero), title = h1 && $('h1 > span:not(.script)', hero), script = h1 && $('.script', h1);
    // Texte bleiben sichtbar und gleiten nur – so ist der Inhalt sofort da (schneller LCP)
    intro.from($$('.pill', hero), { y: 16, duration: 0.8 }, 0);
    // Großer Bildschirm: Wörter einzeln + Schreibschrift "zeichnet" sich. Handy: schlank, damit die Seite sofort fertig ist.
    var big = matchMedia('(min-width: 1021px)').matches;
    if (title && Split && big) {
      var sp = new Split(title, { type: 'words', wordsClass: 'sf-word', aria: 'none' });
      intro.from(sp.words, { yPercent: 35, rotate: 3, duration: 0.9, stagger: 0.05, ease: 'power4.out' }, 0.05);
    } else if (h1) intro.from(h1, { y: 18, duration: 0.9 }, 0.05);
    if (script && big) intro.fromTo(script, { clipPath: 'inset(-20% 100% -20% 0)' }, { clipPath: 'inset(-20% 0% -20% 0)', duration: 1.5, ease: 'power2.inOut' }, 0.75);
    intro.from(heroReveals.filter(function (el) { return el !== h1 && !el.classList.contains('pill'); }), { y: 22, duration: 0.9, stagger: 0.08 }, 0.15);
    $$('.hero-proof strong, .fact strong').forEach(function (el, i) { if (el.getBoundingClientRect().top < innerHeight) countUp(el, 1 + i * 0.12); else ST.create({ trigger: el, start: 'top 90%', once: true, onEnter: function () { countUp(el, 0.2); } }); });
    if ($('#weekDays')) intro.from('#weekDays i', { scale: 0, duration: 0.5, stagger: 0.06, ease: 'back.out(3)' }, 1.3);
    if ($('.phone')) {
      intro.from('.phone', { y: 90, rotate: 14, autoAlpha: 0, duration: 1.4, ease: 'power4.out' }, 0.4);
      if ($('.blob')) intro.from('.blob', { scale: 0.6, autoAlpha: 0, duration: 1.4 }, 0.4);
      intro.from('.float-card', { scale: 0.6, autoAlpha: 0, duration: 0.9, stagger: 0.15, ease: 'back.out(1.8)' }, 1.1);
      intro.from('.badge', { scale: 0, rotate: -120, duration: 1, ease: 'back.out(1.6)' }, 1.25);
    }
    if ($('.lp-stack')) {
      var imgs = $$('.lp-stack img');
      intro.from(imgs, { rotate: 0, xPercent: 0, scale: 0.85, autoAlpha: 0, duration: 1.3, stagger: 0.12, ease: 'power4.out' }, 0.4);
      if ($('.offer')) intro.from('.offer', { y: 40, autoAlpha: 0, duration: 1 }, 1);
    }
    // Parallaxe beim Herausscrollen
    var scrub = { trigger: hero, start: 'top top', end: 'bottom top', scrub: true };
    if ($('.phone')) {
      gsap.fromTo('.phone', { yPercent: 0, rotate: 2 }, { yPercent: -10, rotate: -2, ease: 'none', immediateRender: false, scrollTrigger: scrub });
      if ($('.blob')) gsap.to('.blob', { scale: 1.2, ease: 'none', scrollTrigger: scrub });
      gsap.to('.badge svg', { rotate: 160, ease: 'none', scrollTrigger: scrub });
      if ($('.float-quote')) gsap.to('.float-quote', { yPercent: -40, ease: 'none', scrollTrigger: scrub });
      if ($('.float-like')) gsap.to('.float-like', { yPercent: -60, ease: 'none', scrollTrigger: scrub });
    }
    if ($('.lp-stack')) gsap.to('.lp-stack', { yPercent: -8, ease: 'none', scrollTrigger: scrub });
  }

  /* Alles unterhalb des ersten Bildschirms erst einrichten, wenn der Browser Luft hat
     (die Seite ist dadurch schneller bedienbar). */
  var idle = function (fn) { (window.requestIdleCallback || function (f) { return setTimeout(f, 1); })(fn, { timeout: 1500 }); };
  var later = function () {
  /* ---------- Einblenden: IntersectionObserver + CSS-Übergänge (läuft auf dem Compositor, kein Layout-Stau) ---------- */
    var reveals = $$('.reveal').filter(function (el) { return heroReveals.indexOf(el) < 0 && el.getBoundingClientRect().top > innerHeight; });
    if ('IntersectionObserver' in window) {
      root.classList.add('rv');
      var rio = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { var t = en.target; t.classList.add('in'); rio.unobserve(t); setTimeout(function () { t.classList.remove('rv-wait'); }, 1500); } }); }, { rootMargin: '0px 0px -8% 0px' });
      reveals.forEach(function (el) { el.classList.add('rv-wait'); rio.observe(el); });
      // Sicherheitsnetz bei sehr schnellem Scrollen (z. B. Sprung per Menü): alles Überscrollte sofort zeigen
      var pend = reveals.slice(), tId = 0;
      addEventListener('scroll', function () {
        if (tId || !pend.length) return;
        tId = setTimeout(function () {
          tId = 0;
          pend = pend.filter(function (el) {
            if (el.classList.contains('in')) return false;
            if (el.getBoundingClientRect().top < innerHeight * 0.92 && el.offsetParent !== null) { el.classList.add('in'); rio.unobserve(el); setTimeout(function () { el.classList.remove('rv-wait'); }, 1500); return false; }
            return true;
          });
        }, 200);
      }, { passive: true });
    }
  
    /* Aufwendige Effekte erst vorbereiten, wenn der Abschnitt näher kommt (spart Rechenzeit beim Laden) */
    var near = function (el, fn) {
      if (!('IntersectionObserver' in window)) return fn();
      var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { io.disconnect(); fn(); } }, { rootMargin: '400px 0px' });
      io.observe(el);
    };
  
    /* ---------- ScrollFloat (React Bits) – Überschriften Buchstabe für Buchstabe ---------- */
    if (Split) $$('.section h2, .compare-title').forEach(function (h) {
      if (hero && hero.contains(h)) return;
      gsap.set(h, { opacity: 0 }); // opacity statt visibility: Überschrift bleibt für Screenreader da
      near(h, function () {
      gsap.set(h, { opacity: 1 });
      var s = new Split(h, { type: 'words,chars', wordsClass: 'sf-word', charsClass: 'sf-char' });
      gsap.fromTo(s.chars, { yPercent: 120, scaleY: 2.2, scaleX: 0.7, autoAlpha: 0, transformOrigin: '50% 0%' },
        { yPercent: 0, scaleY: 1, scaleX: 1, autoAlpha: 1, stagger: 0.025, ease: 'back.inOut(2)',
          scrollTrigger: { trigger: h, start: 'top 92%', end: 'top 58%', scrub: 0.6 } });
      });
    });
  
    /* ---------- ScrollReveal (React Bits) – Text füllt sich Wort für Wort ---------- */
    if (Split) $$('.about .lead + .lead, .why-head .lead, .fw-lead .lead, .rh-intro .lead').forEach(function (el) {
      near(el, function () {
        var words = new Split(el, { type: 'words', wordsClass: 'sr-word', aria: 'none' }).words;
        gsap.fromTo(words, { opacity: 0.15 }, { opacity: 1, stagger: 0.05, ease: 'none', scrollTrigger: { trigger: el, start: 'top 85%', end: 'bottom 60%', scrub: true } });
      });
    });
  
    /* ---------- RotatingText (React Bits) ---------- */
    var rot = $('#rotator');
    if (rot) {
      var words = $$('.rot-word', rot);
      words.forEach(function (w) { w.innerHTML = w.textContent.split('').map(function (c) { return '<span class="rc">' + (c === ' ' ? '&nbsp;' : c) + '</span>'; }).join(''); });
      rot.classList.add('rot-fx');
      gsap.set(words, { autoAlpha: 0 }); gsap.set(words[0], { autoAlpha: 1 });
      var wi = 0;
      var next = function () {
        var cur = words[wi]; wi = (wi + 1) % words.length; var nxt = words[wi];
        gsap.to($$('.rc', cur), { yPercent: -110, duration: 0.45, stagger: 0.02, ease: 'power3.in', onComplete: function () { gsap.set(cur, { autoAlpha: 0 }); gsap.set($$('.rc', cur), { yPercent: 0 }); } });
        gsap.set(nxt, { autoAlpha: 1 });
        gsap.fromTo($$('.rc', nxt), { yPercent: 110 }, { yPercent: 0, duration: 0.6, stagger: 0.025, ease: 'back.out(1.6)', delay: 0.25 });
      };
      var loop = gsap.delayedCall(2.4, function tick() { next(); loop = gsap.delayedCall(2.6, tick); });
    }
  
    /* ---------- ScrollVelocity (React Bits) – Marquee reagiert aufs Scrollen ---------- */
    var track = $('.marquee-track');
    if (track) {
      var x = 0, vel = 0, dir = -1, inView = false, skew = gsap.quickSetter(track, 'skewX', 'deg'), setX = gsap.quickSetter(track, 'x', 'px');
      ST.create({ trigger: '.marquee', start: 'top bottom', end: 'bottom top', onToggle: function (s) { inView = s.isActive; },
        onUpdate: function (s) { vel = s.getVelocity(); if (vel) dir = vel > 0 ? -1 : 1; } });
      gsap.ticker.add(function (t, dt) {
        if (!inView) return;
        var half = track.scrollWidth / 2;
        var speed = 0.06 * dt * (1 + Math.min(6, Math.abs(vel) / 300));
        x += speed * dir; if (x <= -half) x += half; if (x > 0) x -= half;
        setX(x); skew(gsap.utils.clamp(-8, 8, -vel / 250)); vel *= 0.92;
      });
    }
  
    /* ---------- Für wen: Branchen wachsen von unten herein ---------- */
    if ($('.trades')) gsap.from('.trades span', { yPercent: matchMedia('(max-width: 720px)').matches ? 60 : -100, autoAlpha: 0, duration: 1, stagger: 0.07, ease: 'power4.out', scrollTrigger: { trigger: '.trades', start: 'top 82%', once: true } });

    /* ---------- Rhythmus: ein Jahr im Feed – Punkte erscheinen Woche für Woche ---------- */
    if ($('#rhChart')) {
      var tl = gsap.timeline({ scrollTrigger: { trigger: '#rhChart', start: 'top 78%', once: true } });
      tl.from('.track-off .dot', { scale: 0, duration: 0.35, stagger: 0.018, ease: 'back.out(3)' }, 0)
        .from('.track-on .dot', { scale: 0, duration: 0.35, stagger: 0.018, ease: 'back.out(3)' }, 0.25)
        .from('.zzz', { autoAlpha: 0, y: 10, duration: 0.6 }, 1.1)
        .from('.chart-now', { scaleY: 0, transformOrigin: '50% 0%', duration: 0.6, ease: 'power2.out' }, 0.9);
    }

    /* ---------- Ergebnis-Zahlen ---------- */
    $$('.result strong').forEach(function (el) {
      if (!/^\d/.test(el.textContent.trim())) return;
      ST.create({ trigger: el, start: 'top 88%', once: true, onEnter: function () { countUp(el, 0.1); } });
    });
  
    /* ---------- Ablauf: Linie folgt dem Scrollen ---------- */
    $$('.steps').forEach(function (steps) {
      ST.create({ trigger: steps, start: 'top 75%', end: 'bottom 55%', scrub: true, onUpdate: function (s) { steps.style.setProperty('--p', s.progress.toFixed(3)); } });
    });
  
    /* ---------- Pakete: Zeilen nacheinander ---------- */
    if ($('.plans tbody')) gsap.from('.plans tbody tr', { x: -24, autoAlpha: 0, duration: 0.6, stagger: 0.04, ease: 'power2.out', scrollTrigger: { trigger: '.plans', start: 'top 80%', once: true } });
  
    /* ---------- Bilder: leichter Zoom ---------- */
    if ($('.about-img img')) gsap.fromTo('.about-img img', { scale: 1.12 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: '.about-frame', start: 'top bottom', end: 'bottom 40%', scrub: true } });
  
    /* ---------- Footer-Schriftzug ---------- */
    var fb = $('#footerBig');
    if (fb && Split) {
      var fs = new Split(fb, { type: 'chars', charsClass: 'sf-char', aria: 'none' });
      gsap.from(fs.chars, { yPercent: 100, autoAlpha: 0, stagger: 0.04, ease: 'power3.out', scrollTrigger: { trigger: fb, start: 'top 98%', end: 'bottom 85%', scrub: 0.8 } });
    }
    ST.refresh();
  };
  if (document.readyState === 'complete') idle(later); else addEventListener('load', function () { idle(later); });

  /* Positionen neu berechnen, sobald Bilder & Schriften geladen sind */
  addEventListener('load', function () { ST.refresh(); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { ST.refresh(); });
})();
