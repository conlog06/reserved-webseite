<?php
/* Reserved. – Vor-Launch-Seite (wird von gate.php eingebunden)
   @var array $c  @var ?string $msg  @var string $msgType  @var bool $openCode */
$P = $c['prelaunch']; $S = $c['site'];
$launch = rs_ts($c['launch_at']);
$ig = rs_ig_data();
$imgs = $ig ? array_map(fn($p) => rs_url($p['img']), array_slice($ig['posts'], 0, 8))
            : array_map(fn($n) => rs_url("assets/img/$n.webp"), ['post-0-cover', 'post-1', 'post-2', 'post-3', 'post-loesung', 'post-4', 'post-5', 'post-dm']);
$col = fn(array $list) => implode('', array_map(fn($u) => '<img src="' . e($u) . '" alt="" loading="lazy" decoding="async" width="864" height="1080">', array_merge($list, $list)));
$half = (int)ceil(count($imgs) / 2);
$v = fn(string $f) => e(rs_url($f)) . '?v=' . (int)@filemtime(rs_root() . '/' . $f);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Reserved. – <?= e($P['kicker']) ?></title>
<meta name="description" content="<?= e($P['text']) ?>">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#F4F1EA">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%23B8593F'/%3E%3Ctext x='32' y='43' text-anchor='middle' font-family='Georgia,serif' font-size='30' fill='%23F4F1EA'%3ER.%3C/text%3E%3C/svg%3E">
<link rel="apple-touch-icon" href="<?= e(rs_url('assets/img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= $v('assets/css/style.css') ?>">
</head>
<body class="pl-body">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></symbol>
  <symbol id="i-insta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/></symbol>
</svg>
<div class="pl">
  <main class="pl-main">
    <a class="logo pl-logo" href="<?= e(rs_url('')) ?>">Reserved<span>.</span></a>
    <span class="pill pl-in"><span class="dot"></span><?= e($P['kicker']) ?></span>
    <h1 class="pl-title pl-in"><span class="pl-t1"><?= e($P['title']) ?></span> <span class="script"><?= e($P['script']) ?></span></h1>
    <p class="lead pl-in"><?= nl2br(e($P['text'])) ?></p>

    <?php if ($launch && $launch > time()): ?>
    <div class="pl-count pl-in" id="count" data-target="<?= $launch * 1000 ?>" role="timer" aria-label="Zeit bis zum Start">
      <?php foreach (['d' => 'Tage', 'h' => 'Stunden', 'm' => 'Minuten', 's' => 'Sekunden'] as $k => $l): ?><div class="pl-unit"><span class="pl-num" data-k="<?= $k ?>"><span>00</span></span><span class="pl-lbl"><?= $l ?></span></div><?php endforeach; ?>
    </div>
    <p class="pl-when pl-in">Start: <?= e(rs_date_de($launch, true)) ?></p>
    <?php endif; ?>

    <?php if ($msg && !$openCode): ?><p class="pl-msg <?= e($msgType) ?>" role="status"><?= e($msg) ?></p><?php endif; ?>
    <?php if (!empty($P['signup']) && !($msg && $msgType === 'ok' && !$openCode)): ?>
    <form class="pl-form pl-in" method="post" action="<?= e(rs_url('')) ?>">
      <input type="hidden" name="action" value="notify">
      <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="pl-notify">
        <label for="pl-mail" class="sr">E-Mail-Adresse</label>
        <input type="email" id="pl-mail" name="email" placeholder="Deine E-Mail-Adresse" required autocomplete="email" inputmode="email" autocapitalize="off">
        <button class="btn btn-primary" type="submit">Benachrichtigen</button>
      </div>
      <label class="pl-consent"><input type="checkbox" name="consent" value="1" required><span>Ich möchte einmalig per E-Mail informiert werden, wenn die Seite online ist. Danach wird meine Adresse gelöscht.</span></label>
    </form>
    <?php endif; ?>

    <div class="pl-ways pl-in">
      <a href="https://www.instagram.com/<?= e($S['instagram']) ?>/" target="_blank" rel="noopener"><svg><use href="#i-insta"/></svg>@<?= e($S['instagram']) ?></a>
      <a href="mailto:<?= e($S['email']) ?>"><svg><use href="#i-mail"/></svg><?= e($S['email']) ?></a>
    </div>

    <details class="pl-code pl-in"<?= $openCode ? ' open' : '' ?>>
      <summary>Du hast einen Zugangscode?</summary>
      <?php if ($msg && $openCode): ?><p class="pl-msg error" role="alert"><?= e($msg) ?></p><?php endif; ?>
      <form method="post" action="<?= e(rs_url('')) ?>" class="pl-notify"><input type="hidden" name="action" value="code">
        <label for="pl-code" class="sr">Zugangscode</label>
        <input id="pl-code" name="code" placeholder="XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
        <button class="btn btn-ghost" type="submit">Öffnen</button></form>
    </details>

    <footer class="pl-foot"><span>© <?= date('Y') ?> Reserved.</span><button type="button" class="linklike" data-open="impressum">Impressum</button><button type="button" class="linklike" data-open="datenschutz">Datenschutz</button></footer>
  </main>
  <aside class="pl-wall" aria-hidden="true">
    <div class="pl-col"><div class="pl-track"><?= $col(array_slice($imgs, 0, $half)) ?></div></div>
    <div class="pl-col up"><div class="pl-track"><?= $col(array_slice($imgs, $half)) ?></div></div>
  </aside>
</div>

<dialog id="impressum" class="legal"><div class="dlg" data-lenis-prevent><button type="button" class="dlg-close" data-close aria-label="Schließen"><svg><use href="#i-close"/></svg></button><h2>Impressum</h2><p class="sub">Angaben gemäß § 5 DDG</p><?= rs_render_impressum($S) ?></div></dialog>
<dialog id="datenschutz" class="legal"><div class="dlg" data-lenis-prevent><button type="button" class="dlg-close" data-close aria-label="Schließen"><svg><use href="#i-close"/></svg></button><h2>Datenschutz</h2><p class="sub">Datenschutzerklärung</p><?= rs_render_datenschutz($S, $c) ?>
  <h4>Benachrichtigung zum Start</h4><p>Wenn du dich auf dieser Seite einträgst, speichern wir deine E-Mail-Adresse ausschließlich, um dich einmalig über den Start der Webseite zu informieren (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;a DSGVO). Nach dem Versand wird sie gelöscht. Du kannst die Einwilligung jederzeit per E-Mail widerrufen.</p></div></dialog>

<script src="<?= $v('assets/js/vendor/gsap.min.js') ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Dialoge
  document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
  document.querySelectorAll('dialog').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); });
  // Einstieg
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (window.gsap && !reduce) {
    gsap.from('.pl-logo', { y: -20, autoAlpha: 0, duration: .8, ease: 'power3.out' });
    gsap.from('.pl-in', { y: 34, autoAlpha: 0, duration: 1, stagger: .1, ease: 'power3.out', delay: .15 });
    gsap.from('.pl-col', { yPercent: 12, autoAlpha: 0, duration: 1.4, stagger: .15, ease: 'power3.out', delay: .2 });
    var sc = document.querySelector('.pl-title .script');
    if (sc) gsap.fromTo(sc, { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 1.6, ease: 'power2.inOut', delay: .7 });
  }
  // Countdown
  var box = document.getElementById('count'); if (!box) return;
  var target = +box.dataset.target, els = {}, last = {}, ready = false;
  box.querySelectorAll('.pl-num').forEach(function (n) { els[n.dataset.k] = n; });
  function set(k, v) {
    v = (v < 10 ? '0' : '') + v; if (last[k] === v) return; last[k] = v;
    var s = document.createElement('span'); s.textContent = v; els[k].replaceChildren(s);
    if (ready && window.gsap && !reduce) gsap.fromTo(s, { yPercent: -100, autoAlpha: 0 }, { yPercent: 0, autoAlpha: 1, duration: .5, ease: 'back.out(2)' });
  }
  (function tick() {
    var ms = Math.max(0, target - Date.now()), t = Math.floor(ms / 1000);
    set('d', Math.floor(t / 86400)); set('h', Math.floor(t % 86400 / 3600)); set('m', Math.floor(t % 3600 / 60)); set('s', t % 60);
    ready = true;
    if (ms <= 0) return setTimeout(function () { location.reload(); }, 1500);
    setTimeout(tick, 1000 - (Date.now() % 1000));
  })();
});
</script>
</body>
</html>
