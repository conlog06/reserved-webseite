<?php
/* ==========================================================================
   reserved.social – Schnittstelle für die Webseite
   POST ?a=anfrage   Anfrage aus dem Formular (JSON)
   POST ?a=t         Statistik-Signal (gesehene Abschnitte, Klicks, Verweildauer)
   GET  ?a=termine   freie Termine · POST ?a=buchen Termin buchen
   ========================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$out = function (array $d, int $code = 200) { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

/* ---------- Links aus den Funnel-Mails (GET) ---------- */
$a = (string)($_GET['a'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array($a, ['bestaetigen', 'plan', 'abmelden'], true)) {
    $t = (string)($_GET['t'] ?? '');
    if ($a === 'bestaetigen') {
        $s = rs_funnel_confirm($t);
        if (!$s) rs_funnel_page('Link ungültig', 'Dieser Bestätigungslink ist ungültig oder abgelaufen. Trag dich einfach noch einmal ein.', rs_url('plan/'), 'Zum Download');
        rs_stats_click('plan-confirm');
        header('Location: ' . rs_url('plan/danke/?t=' . $s['token']), true, 303); exit;
    }
    if ($a === 'plan') {
        $d = (string)($_GET['d'] ?? '');
        $s = $d !== '' ? (rs_funnel_mode() !== 'mail' ? rs_funnel_find_dl($d) : null) : rs_funnel_find($t);
        if (!$s || ($d === '' && !$s['confirmed'])) rs_funnel_page('Download nicht möglich', 'Bitte bestätige zuerst deine E-Mail-Adresse – den Link findest du in unserer Mail.', rs_url('plan/'), 'Zurück');
        rs_funnel_update($s['token'], function (&$x) { $x['downloads']++; });
        rs_stats_click('plan-download');
        $f = rs_funnel_pdf();
        header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . rs_slug(rs_config()['funnel']['title']) . '.pdf"');
        header('Content-Length: ' . filesize($f)); header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
        readfile($f); exit;
    }
    $s = rs_funnel_unsub($t);
    rs_funnel_page($s ? 'Du bist abgemeldet' : 'Link ungültig', $s ? 'Du bekommst keine E-Mails mehr von uns. Schade, dass du gehst – alles Gute!' : 'Dieser Abmeldelink ist ungültig.', rs_url(''), 'Zur Startseite');
}
/* ---------- Terminbuchung (GET): freie Termine, Kalender-Datei, Absage ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array($a, ['termine', 'termin_ics', 'termin_absagen'], true)) {
    if ($a === 'termine') {
        $B = rs_config()['booking'];
        if (empty($B['on'])) $out(['ok' => false, 'error' => 'Die Terminbuchung ist gerade nicht verfügbar.'], 404);
        $types = array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'min' => (int)$t['min'], 'desc' => (string)($t['desc'] ?? ''), 'where' => (string)($t['where'] ?? '')], rs_booking_types($B));
        $art = (string)($_GET['art'] ?? ''); if (!rs_booking_type($art, $B)) $art = $types[0]['id'] ?? '';
        $out(['ok' => true, 'types' => $types, 'art' => $art, 'days' => $art !== '' ? rs_booking_slots($art) : []]);
    }
    $t = (string)($_GET['t'] ?? '');
    $b = rs_booking_find($t);
    if ($a === 'termin_ics') {
        if (!$b) rs_funnel_page('Termin nicht gefunden', 'Dieser Link ist ungültig.', rs_url(''), 'Zur Startseite');
        header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="termin-reserved-social.ics"'); header('X-Robots-Tag: noindex');
        echo rs_booking_ics($b, rs_is_admin()); exit;
    }
    if (!$b) rs_funnel_page('Termin nicht gefunden', 'Dieser Absage-Link ist ungültig.', rs_url(''), 'Zur Startseite');
    if ($b['status'] !== 'ok') rs_funnel_page('Termin abgesagt', 'Dieser Termin ist bereits abgesagt. Du kannst dir jederzeit einen neuen aussuchen.', rs_url('termin/?art=' . $b['type']), 'Neuen Termin buchen');
    if ((int)$b['start'] < time()) rs_funnel_page('Termin vorbei', 'Dieser Termin liegt in der Vergangenheit.', rs_url(''), 'Zur Startseite');
    if (($_GET['ja'] ?? '') !== '1') rs_funnel_page('Termin absagen?', $b['type_name'] . ' · ' . rs_booking_when($b) . '. Möchtest du diesen Termin wirklich absagen?', rs_url('_reserved/api.php?a=termin_absagen&t=' . $b['token'] . '&ja=1'), 'Ja, Termin absagen');
    rs_booking_cancel($b['token'], 'kunde');
    rs_funnel_page('Termin abgesagt', 'Schade – der Termin ist abgesagt. Wenn du magst, such dir einfach einen neuen aus.', rs_url('termin/?art=' . $b['type']), 'Neuen Termin buchen');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $out(['ok' => false, 'error' => 'Nur POST erlaubt.'], 405);
// Nur Anfragen von der eigenen Seite annehmen
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))) $out(['ok' => false, 'error' => 'Nicht erlaubt.'], 403);

$raw = (string)file_get_contents('php://input', false, null, 0, 65536);
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$a = (string)($_GET['a'] ?? '');

if ($a === 't') {
    if (!empty(rs_config()['stats_on']) && !rs_throttled('beacon', 120, 3600)) { rs_stats_event($in); rs_hit('beacon', 3600); }
    http_response_code(204); exit;
}

if ($a === 'plan') {
    if (!empty($in['website'])) $out(['ok' => true]);                                                     // Spam-Falle
    if (rs_throttled('plan', 5, 3600)) $out(['ok' => false, 'error' => 'Zu viele Versuche. Bitte später erneut probieren.'], 429);
    [$ok, $res] = rs_funnel_signup($in);
    if (!$ok) $out(['ok' => false, 'error' => $res], 400);
    rs_hit('plan', 3600);
    $out(rs_funnel_mode() === 'mail' ? ['ok' => true] : ['ok' => true, 'next' => rs_url('plan/danke/?d=' . $res['dl'])]);
}

if ($a === 'buchen') {
    if (!empty($in['website'])) $out(['ok' => true]);                                                     // Spam-Falle
    if (rs_throttled('buchen', 6, 3600)) $out(['ok' => false, 'error' => 'Zu viele Versuche. Bitte später erneut probieren.'], 429);
    [$ok, $res] = rs_booking_create($in);
    if (!$ok) $out(['ok' => false, 'error' => $res], 400);
    rs_hit('buchen', 3600);
    $out(['ok' => true, 'when' => rs_booking_when($res), 'type' => $res['type_name'], 'where' => $res['where'], 'ics' => rs_booking_links($res)['ics']]);
}

if ($a === 'anfrage') {
    if (!empty($in['website'])) $out(['ok' => true, 'rec' => 'Standard', 'why' => '']);                 // Spam-Falle
    if ((int)($in['t'] ?? 0) > 0 && (int)$in['t'] < 2500) $out(['ok' => false, 'error' => 'Das ging zu schnell – bitte versuche es noch einmal.'], 400);
    if (rs_throttled('anfrage', 5, 3600)) $out(['ok' => false, 'error' => 'Zu viele Anfragen in kurzer Zeit. Bitte später erneut versuchen.'], 429);
    [$ok, $res] = rs_lead_create($in);
    if (!$ok) $out(['ok' => false, 'error' => $res], 400);
    rs_hit('anfrage', 3600);
    $out(['ok' => true, 'rec' => $res['rec'], 'why' => $res['why']]);
}
$out(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
