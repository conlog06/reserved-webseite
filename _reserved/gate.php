<?php
/* ==========================================================================
   reserved.social – Torwächter: liefert Startseite und /angebot/ aus.
   live        → Seite mit den Inhalten aus dem Admin
   prelaunch   → Vor-Launch-Seite mit Countdown (geht zum Zeitpunkt automatisch online)
   maintenance → Wartungsseite (HTTP 503)
   Admins und Personen mit Zugangslink sehen immer die echte Seite.
   ========================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';

$c = rs_config();
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ---------- Welche Seite? ---------- */
$uri = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$base = rs_base();
if ($base !== '' && strpos($uri, $base) === 0) $uri = substr($uri, strlen($base));
$isLp = (bool)preg_match('~^/angebot(/|/index\.html)?$~', $uri);
$isCheck = (bool)preg_match('~^/start(/|/index\.html)?$~', $uri);
$isAbout = (bool)preg_match('~^/ueber-mich(/|/index\.html)?$~', $uri);
$isPlan = (bool)preg_match('~^/plan(/|/index\.html)?$~', $uri);
$isDanke = (bool)preg_match('~^/plan/danke(/|/index\.html)?$~', $uri);
$isUgc = (bool)preg_match('~^/ugc(/|/index\.html)?$~', $uri);
$isTermin = (bool)preg_match('~^/termin(/|/index\.html)?$~', $uri);
$page = $isLp ? 'angebot/index.html' : ($isCheck ? 'start/index.html' : ($isAbout ? 'ueber-mich/index.html' : ($isPlan ? 'plan/index.html' : ($isDanke ? 'plan/danke/index.html' : ($isUgc ? 'ugc/index.html' : ($isTermin ? 'termin/index.html' : 'index.html'))))));
$here = rs_url($isLp ? 'angebot/' : ($isCheck ? 'start/' : ($isAbout ? 'ueber-mich/' : ($isPlan ? 'plan/' : ($isDanke ? 'plan/danke/' : ($isUgc ? 'ugc/' : ($isTermin ? 'termin/' : '')))))));

/* ---------- Automatischer Launch ---------- */
$mode = rs_effective_mode($c);
if ($c['mode'] === 'prelaunch' && $mode === 'live') {
    $c['mode'] = 'live'; rs_save_config($c);
    $launchedNow = true;
}

/* ---------- Zugangslink & Formulare der Vor-Launch-Seite ---------- */
$msg = null; $msgType = 'error'; $openCode = false;
if (isset($_GET['zugang'])) {
    if (rs_throttled('code')) { $msg = 'Zu viele Versuche. Bitte in 15 Minuten erneut probieren.'; $openCode = true; }
    else {
        $code = rs_find_code((string)$_GET['zugang']);
        if ($code && rs_code_valid($code)) {
            rs_touch_code($code['id']);
            $days = 30;
            if (!empty($code['expires']) && ($t = rs_ts($code['expires']))) $days = max(1, (int)ceil(($t - time()) / 86400));
            rs_grant_cookie('code:' . $code['id'], $days);
            header('Location: ' . $here, true, 303); exit;
        }
        rs_hit('code'); $msg = 'Dieser Zugangslink ist ungültig oder abgelaufen.'; $openCode = true;
    }
}
if (isset($_GET['zugang-beenden'])) { rs_clear_cookie(); header('Location: ' . $here, true, 303); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $mode === 'prelaunch') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'code') {
        $openCode = true;
        if (rs_throttled('code')) $msg = 'Zu viele Versuche. Bitte in 15 Minuten erneut probieren.';
        elseif (($code = rs_find_code((string)($_POST['code'] ?? ''))) && rs_code_valid($code)) {
            rs_touch_code($code['id']); rs_grant_cookie('code:' . $code['id']);
            header('Location: ' . $here, true, 303); exit;
        } else { rs_hit('code'); $msg = 'Der Code stimmt nicht. Bitte prüfe die Schreibweise.'; }
    } elseif ($action === 'notify' && !empty($c['prelaunch']['signup'])) {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!empty($_POST['website'])) { $msg = 'Danke!'; $msgType = 'ok'; }
        elseif (rs_throttled('notify', 5, 3600)) $msg = 'Zu viele Anmeldungen von diesem Anschluss. Bitte später erneut versuchen.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = 'Bitte gib eine gültige E-Mail-Adresse ein.';
        elseif (empty($_POST['consent'])) $msg = 'Bitte bestätige noch die Einwilligung zur Benachrichtigung.';
        else {
            rs_locked('signups', function () use ($email) {
                $list = rs_signups();
                foreach ($list as $s) if (strcasecmp($s['email'], $email) === 0) return;
                $list[] = ['email' => mb_substr($email, 0, 200), 'ts' => time()];
                rs_write('signups', $list);
            });
            rs_hit('notify', 3600);
            $msg = 'Danke! Wir sagen dir Bescheid, sobald die Seite online ist.'; $msgType = 'ok';
        }
    }
}

/* ---------- Entscheidung ---------- */
$admin = rs_is_admin();
$who = rs_cookie_who();
$bypass = $admin || $who !== null;
$track = function (string $p) use ($admin, $c) {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    if (!$admin && !empty($c['stats_on'])) rs_stats_track($p);
};

if ($admin && ($_GET['vorschau'] ?? '') === 'launch') { header('Cache-Control: no-store'); header('X-Robots-Tag: noindex'); require __DIR__ . '/prelaunch.php'; exit; }
if ($mode === 'maintenance' && !$bypass) {
    http_response_code(503); header('Retry-After: 3600'); header('X-Robots-Tag: noindex');
    $mail = e($c['site']['email']); $ig = e($c['site']['instagram']);
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>reserved.social – gleich zurück</title>'
       . '<link rel="stylesheet" href="' . e(rs_url('assets/css/style.css')) . '"><style>body{display:grid;place-items:center;min-height:100vh;min-height:100dvh;padding:24px;text-align:center}main{max-width:560px}h1{font-size:clamp(2.6rem,9vw,4.4rem);margin:18px 0}p{color:var(--mokka)}.btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px}</style></head>'
       . '<body><main><span class="logo">reserved<span>.social</span></span><h1>Wir sind <span class="script">gleich zurück.</span></h1><p>' . e($c['maintenance_text']) . '</p>'
       . '<div class="btns"><a class="btn btn-primary" href="mailto:' . $mail . '">E-Mail schreiben</a><a class="btn btn-ghost" href="https://ig.me/m/' . $ig . '">Instagram-DM</a></div></main></body></html>';
    $track('(wartung)'); exit;
}
if ($mode === 'prelaunch' && !$bypass) {
    header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
    require __DIR__ . '/prelaunch.php';
    $track('(launch)'); exit;
}
if ($isLp && empty($c['angebot']['on']) && !$admin) { header('Location: ' . rs_url(''), true, 302); exit; }
if (($isPlan || $isDanke) && empty($c['funnel']['on']) && !$admin) { header('Location: ' . rs_url(''), true, 302); exit; }
if ($isTermin && empty($c['booking']['on']) && !$admin) { header('Location: ' . rs_url('#kontakt'), true, 302); exit; }
if ($isUgc && empty($c['ugc']['on']) && !$admin) { header('Location: ' . rs_url('ueber-mich/'), true, 302); exit; }
if ($isCheck && empty($c['check']['on']) && !$admin) { header('Location: ' . rs_url('#kontakt'), true, 302); exit; }

/* ---------- Echte Seite ---------- */
$tpl = (string)file_get_contents(rs_root() . '/' . $page);
$html = $isLp ? rs_render_angebot($tpl, $c) : ($isCheck ? rs_render_check($tpl, $c) : ($isAbout ? rs_render_about($tpl, $c) : ($isPlan || $isDanke ? rs_render_plan($tpl, $c, $isDanke) : ($isUgc ? rs_render_ugc($tpl, $c) : ($isTermin ? rs_render_termin($tpl, $c) : rs_render_page($tpl, $c, $admin))))));
if ($admin || $bypass) {
    header('Cache-Control: no-store');
    if ($mode !== 'live') header('X-Robots-Tag: noindex');
    $label = $mode === 'maintenance' ? 'Wartungsmodus – nur du siehst die Seite' : ($mode === 'prelaunch' ? 'Vor-Launch – Besucher sehen die Countdown-Seite' : 'Als Admin angemeldet (wirst nicht gezählt)');
    if (!$admin) $label = 'Vorschau mit Zugangslink';
    $link = $admin ? '<a href="' . e(rs_url('admin/')) . '" style="color:#D98A73">Admin öffnen</a>' : '<a href="?zugang-beenden" style="color:#D98A73">Vorschau beenden</a>';
    $html = (string)preg_replace('~</body>~i', '<style>.rs-adminbar{bottom:calc(16px + env(safe-area-inset-bottom))}@media(max-width:720px){.rs-adminbar{bottom:calc(84px + env(safe-area-inset-bottom))}}</style><div class="rs-adminbar" style="position:fixed;left:50%;transform:translateX(-50%);z-index:9999;display:flex;gap:10px;align-items:center;padding:10px 16px;border-radius:999px;background:#2B2622;color:#F4F1EA;font:600 13px/1.2 system-ui,sans-serif;box-shadow:0 12px 30px rgba(0,0,0,.35);white-space:nowrap;max-width:calc(100vw - 24px);overflow:hidden;text-overflow:ellipsis"><span style="width:8px;height:8px;border-radius:50%;background:#D98A73;flex-shrink:0"></span>' . e($label) . ' · ' . $link . '</div></body>', $html, 1);
}
echo rs_inline_css($html);
$track($page);
if (!empty($launchedNow)) rs_launch_notify();
rs_ig_maybe_sync();
