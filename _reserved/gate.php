<?php
/* ==========================================================================
   Reserved. – Torwächter: liefert index.html mit den Inhalten aus dem Admin aus.
   Modus "Wartung" zeigt Besuchern eine Wartungsseite (Admins sehen die echte Seite).
   ========================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = rs_config();
$admin = rs_is_admin();
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($c['mode'] === 'maintenance' && !$admin) {
    http_response_code(503);
    header('Retry-After: 3600');
    header('X-Robots-Tag: noindex');
    $mail = e($c['site']['email']); $ig = e($c['site']['instagram']);
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Reserved. – gleich zurück</title>'
       . '<link rel="stylesheet" href="' . e(rs_url('assets/css/style.css')) . '"><style>body{display:grid;place-items:center;min-height:100vh;min-height:100dvh;padding:24px;text-align:center}main{max-width:560px}h1{font-size:clamp(2.6rem,9vw,4.4rem);margin:18px 0}p{color:var(--mokka)}.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px}</style></head>'
       . '<body><main><span class="logo">Reserved<span>.</span></span><h1>Wir sind <span class="script">gleich zurück.</span></h1><p>' . e($c['maintenance_text']) . '</p>'
       . '<div class="btns"><a class="btn btn-primary" href="mailto:' . $mail . '">E-Mail schreiben</a><a class="btn btn-ghost" href="https://ig.me/m/' . $ig . '">Instagram-DM</a></div></main></body></html>';
    if (!empty($c['stats_on'])) { if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); rs_stats_track('(wartung)'); }
    exit;
}

$html = rs_render_page((string)file_get_contents(rs_root() . '/index.html'), $c, $admin);
if ($admin) {
    header('Cache-Control: no-store');
    $label = $c['mode'] === 'maintenance' ? 'Wartungsmodus aktiv – nur du siehst die Seite' : 'Du bist als Admin angemeldet (wirst nicht gezählt)';
    $html = (string)preg_replace('~</body>~i', '<a href="' . e(rs_url('admin/')) . '" style="position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom));transform:translateX(-50%);z-index:9999;display:flex;gap:10px;align-items:center;padding:10px 16px;border-radius:999px;background:#2B2622;color:#F4F1EA;font:600 13px/1.2 system-ui,sans-serif;text-decoration:none;box-shadow:0 12px 30px rgba(0,0,0,.35);white-space:nowrap"><span style="width:8px;height:8px;border-radius:50%;background:#D98A73"></span>' . e($label) . ' · Admin öffnen</a></body>', $html, 1);
}
echo $html;

if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
if (!$admin && !empty($c['stats_on'])) rs_stats_track('index.html');
rs_ig_maybe_sync();
