<?php
/* ==========================================================================
   Reserved. – Admin-Bereich  (…/admin/)
   Übersicht · Anfragen · Statistik · Inhalte · Instagram · Einstellungen
   ========================================================================== */
declare(strict_types=1);
require dirname(__DIR__) . '/_reserved/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

rs_session();
$c = rs_config();
$self = (string)strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$tabs = ['start' => 'Übersicht', 'anfragen' => 'Anfragen', 'statistik' => 'Statistik', 'inhalte' => 'Inhalte', 'seiten' => 'Seiten', 'instagram' => 'Instagram', 'einstellungen' => 'Einstellungen'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'start';

function flash(string $t, string $type = 'ok'): void { $_SESSION['rs_flash'] = [$t, $type]; }
function back(string $q = ''): void {
    global $self, $tab;
    header('Location: ' . $self . '?tab=' . urlencode($_POST['tab'] ?? $tab) . $q, true, 303); exit;
}
function setup_code(): string {
    $file = RS_DATA . '/einrichtungscode.php';
    if (!is_file($file)) {
        if (!is_dir(RS_DATA)) mkdir(RS_DATA, 0750, true);
        $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
        for ($i = 0; $i < 16; $i++) $s .= $a[random_int(0, strlen($a) - 1)] . (in_array($i, [3, 7, 11], true) ? '-' : '');
        file_put_contents($file, RS_GUARD . "Einrichtungscode für den Reserved.-Admin-Bereich:\n$s\n", LOCK_EX);
    }
    preg_match('/[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/', (string)file_get_contents($file), $m);
    return $m[0] ?? '';
}
$norm = fn(string $s) => strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $s));
$isAdmin = !empty($_SESSION['rs_admin']) && ($_SESSION['rs_admin_until'] ?? 0) > time();
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = (string)($_POST['action'] ?? '');
$error = null;

/* ---------- Ersteinrichtung & Login ---------- */
if ($c['admin_hash'] === '') {
    $expected = setup_code();
    if ($post && $action === 'setup') {
        $pw = (string)($_POST['password'] ?? '');
        if (rs_throttled('setup', 5)) $error = 'Zu viele Versuche. Bitte 15 Minuten warten.';
        elseif (!hash_equals($norm($expected), $norm((string)($_POST['setup_code'] ?? '')))) { rs_hit('setup'); $error = 'Der Einrichtungscode stimmt nicht.'; }
        elseif (strlen($pw) < 10) $error = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        elseif ($pw !== ($_POST['password2'] ?? '')) $error = 'Die Passwörter stimmen nicht überein.';
        else {
            $c['admin_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            if ($c['secret'] === '') $c['secret'] = bin2hex(random_bytes(32));
            rs_save_config($c);
            @unlink(RS_DATA . '/einrichtungscode.php');
            session_regenerate_id(true);
            $_SESSION['rs_admin'] = true; $_SESSION['rs_admin_until'] = time() + 12 * 3600;
            flash('Admin-Bereich eingerichtet. Willkommen!'); back();
        }
    }
    render_auth('setup', $error); exit;
}
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: ' . $self, true, 303); exit; }
if (!$isAdmin) {
    if ($post && $action === 'login') {
        if (rs_throttled('login', 6)) $error = 'Zu viele Fehlversuche. Bitte 15 Minuten warten.';
        elseif (password_verify((string)($_POST['password'] ?? ''), $c['admin_hash'])) {
            session_regenerate_id(true);
            $_SESSION['rs_admin'] = true; $_SESSION['rs_admin_until'] = time() + 12 * 3600;
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? $self), true, 303); exit;
        } else { rs_hit('login'); $error = 'Passwort falsch.'; }
    }
    render_auth('login', $error); exit;
}
$_SESSION['rs_admin_until'] = time() + 12 * 3600;

/* ==========================================================================
   Aktionen
   ========================================================================== */
if ($post) {
    if (!rs_check_csrf()) { flash('Sitzung abgelaufen – bitte erneut versuchen.', 'error'); back(); }
    $S = &$c['site'];
    $str = fn(string $k, int $max = 200) => rs_cut(str_replace("\r", '', (string)($_POST[$k] ?? '')), $max);
    $list = fn(string $k) => array_values(array_filter((array)($_POST[$k] ?? []), 'is_array'));
    switch ($action) {
        /* ----- Anfragen ----- */
        case 'lead_status':
        case 'lead_note':
        case 'lead_delete':
            $id = (string)($_POST['id'] ?? '');
            rs_locked('leads', function () use ($action, $id, $str) {
                $l = rs_leads();
                foreach ($l as $k => &$x) {
                    if ($x['id'] !== $id) continue;
                    if ($action === 'lead_status' && isset(rs_lead_status()[$_POST['status'] ?? ''])) {
                        $x['status'] = $_POST['status']; $x['notes'][] = ['ts' => time(), 't' => 'Status: ' . rs_lead_status()[$x['status']], 'sys' => true];
                    }
                    if ($action === 'lead_note' && ($t = $str('note', 2000)) !== '') $x['notes'][] = ['ts' => time(), 't' => $t];
                    if ($action === 'lead_delete') unset($l[$k]);
                    if ($action !== 'lead_delete') $x['seen'] = true;
                }
                unset($x);
                rs_save_leads($l);
            });
            flash($action === 'lead_delete' ? 'Anfrage gelöscht.' : 'Gespeichert.');
            back($action === 'lead_delete' ? '' : '&id=' . urlencode($id));
        case 'lead_settings':
            $c['leads']['autoreply'] = !empty($_POST['autoreply']);
            $c['leads']['keep_months'] = max(0, min(60, (int)($_POST['keep_months'] ?? 12)));
            rs_save_config($c); flash('Einstellungen für Anfragen gespeichert.'); back();
        case 'lead_export':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="reserved-anfragen-' . date('Y-m-d') . '.csv"');
            $o = rs_lead_options(); $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Datum', 'Status', 'Priorität', 'Empfehlung', 'Name', 'Unternehmen', 'Branche', 'Ort', 'E-Mail', 'Telefon', 'Instagram', 'Kontakt per', 'Ziele', 'Social Media', 'Umfang', 'Start', 'Budget', 'Paket', 'Nachricht', 'Herkunft', 'Gerät'], ';');
            foreach (array_reverse(rs_leads()) as $x) {
                $d = $x['data'];
                fputcsv($f, [date('d.m.Y H:i', $x['created']), rs_lead_status()[$x['status']] ?? $x['status'], $x['score'], $x['rec'], $d['name'], $d['firma'],
                    $d['branche'] === 'andere' ? $d['branche_text'] : ($o['branche'][$d['branche']] ?? ''), $d['ort'], $d['email'], $d['telefon'], $d['insta'],
                    $o['kontakt'][$d['kontakt']] ?? '', implode(', ', array_map(fn($z) => $o['ziele'][$z] ?? $z, $d['ziele'])), $o['stand'][$d['stand']] ?? '',
                    $o['umfang'][$d['umfang']] ?? '', $o['start'][$d['start']] ?? '', $d['budget'] ?? '', $d['paket'], $d['nachricht'], $x['src'], $x['device']], ';');
            }
            exit;

        /* ----- Inhalte ----- */
        case 'content_hero':
            foreach (['hero_kicker' => 90, 'hero_title' => 120, 'hero_script' => 60, 'hero_lead' => 400, 'response_text' => 160, 'about_lead' => 400, 'about_text' => 1200, 'bar_text' => 180, 'bar_link_text' => 40] as $k => $max) $S[$k] = $str($k, $max);
            $S['bar_on'] = !empty($_POST['bar_on']);
            $link = $str('bar_link', 300); $S['bar_link'] = preg_match('~^(#|/|https?://)~i', $link) ? $link : '#kontakt';
            $S['proof'] = array_map(fn($p) => ['v' => rs_cut((string)($p['v'] ?? ''), 12), 't' => rs_cut((string)($p['t'] ?? ''), 60)], array_slice($list('proof'), 0, 3));
            rs_save_config($c); flash('Texte gespeichert – sofort auf der Webseite sichtbar.'); back('#hero');
        case 'content_plans':
            $S['plans'] = array_map(fn($p) => ['tier' => rs_cut((string)($p['tier'] ?? ''), 30), 'name' => rs_cut((string)($p['name'] ?? ''), 30) ?: 'Paket', 'price' => rs_cut((string)($p['price'] ?? ''), 40)], array_slice($list('plans'), 0, 3));
            $S['plan_rows'] = [];
            foreach ($list('rows') as $r) {
                if (trim((string)($r['l'] ?? '')) === '') continue;
                $S['plan_rows'][] = ['l' => rs_cut((string)$r['l'], 80), 'v' => array_map(fn($i) => rs_cut((string)($r['v'][$i] ?? ''), 30), [0, 1, 2])];
            }
            $S['plans_note'] = $str('plans_note', 160);
            $S['extras'] = [];
            foreach ($list('extras') as $x) if (trim((string)($x['t'] ?? '')) !== '') $S['extras'][] = ['t' => rs_cut((string)$x['t'], 60), 'd' => rs_cut((string)($x['d'] ?? ''), 160), 'p' => rs_cut((string)($x['p'] ?? ''), 40)];
            rs_save_config($c); flash('Pakete gespeichert.'); back('#pakete');
        case 'content_feedback':
            $S['results'] = [];
            foreach (array_slice($list('results'), 0, 3) as $r) $S['results'][] = ['v' => rs_cut((string)($r['v'] ?? ''), 20), 't' => rs_cut((string)($r['t'] ?? ''), 200), 'l' => rs_cut((string)($r['l'] ?? ''), 40)];
            $S['quotes'] = [];
            foreach ($list('quotes') as $q) {
                if (trim((string)($q['t'] ?? '')) === '') continue;
                $S['quotes'][] = ['t' => rs_cut((string)$q['t'], 600), 'n' => rs_cut((string)($q['n'] ?? ''), 60), 'c' => rs_cut((string)($q['c'] ?? ''), 60),
                                  'note' => rs_cut((string)($q['note'] ?? ''), 200), 'blue' => !empty($q['blue']), 'on' => !empty($q['on'])];
            }
            rs_save_config($c); flash('Feedback gespeichert.'); back('#feedback');
        case 'image':
        case 'image_delete':
            $slot = ($_POST['slot'] ?? '') === 'avatar' ? 'avatar' : 'about';
            $anchor = $slot === 'avatar' ? '#fitcheck' : '#ueber';
            if ($slot === 'avatar') $GLOBALS['tab'] = 'seiten';
            $key = 'img_' . $slot;
            $old = (string)($S[$key] ?? '');
            $f = $old !== '' ? rs_root() . '/' . strtok($old, '?') : '';
            if ($action === 'image_delete') { if ($f && is_file($f)) @unlink($f); $S[$key] = ''; rs_save_config($c); flash('Bild entfernt.'); back($anchor); }
            $up = $_FILES['file'] ?? null;
            if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) { flash('Upload fehlgeschlagen – bitte eine Bilddatei wählen (max. 10 MB).', 'error'); back($anchor); }
            $info = @getimagesize($up['tmp_name']);
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext || $up['size'] > 10 * 1024 * 1024) { flash('Nur JPG, PNG oder WebP bis 10 MB.', 'error'); back($anchor); }
            if ($f && is_file($f)) @unlink($f);
            $rel = 'assets/img/' . $slot . '-upload.' . $ext;
            if (!@move_uploaded_file($up['tmp_name'], rs_root() . '/' . $rel)) { flash('Bild konnte nicht gespeichert werden – Schreibrechte für assets/img/ prüfen.', 'error'); back($anchor); }
            @chmod(rs_root() . '/' . $rel, 0644);
            $S[$key] = $rel . '?v=' . time(); rs_save_config($c); flash('Bild aktualisiert.'); back($anchor);
        case 'check':
            $c['check']['on'] = !empty($_POST['fc_on']);
            foreach (['title' => 90, 'text' => 300, 'cta' => 40, 'done_title' => 60, 'done_text' => 300, 'budget_q' => 120, 'budget_sub' => 200] as $k => $max) $c['check'][$k] = $str('fc_' . $k, $max) ?: $c['check'][$k];
            $c['check']['budget_on'] = !empty($_POST['fc_budget_on']);
            $c['check']['budget'] = array_slice(array_values(array_filter(array_map(fn($b) => rs_cut($b, 60), explode("\n", str_replace("\r", '', (string)($_POST['fc_budget'] ?? '')))), fn($b) => $b !== '')), 0, 8);
            rs_save_config($c); flash('Fit-Check gespeichert.'); back('#fitcheck');

        /* ----- Seiten: Status, Vor-Launch, Zugänge, Landingpage ----- */
        case 'status':
            $m = (string)($_POST['mode'] ?? 'live');
            $c['mode'] = in_array($m, ['live', 'prelaunch', 'maintenance'], true) ? $m : 'live';
            $at = trim((string)($_POST['launch_at'] ?? ''));
            $c['launch_at'] = rs_ts($at) ? $at : '';
            $c['auto_launch'] = !empty($_POST['auto_launch']);
            $c['prelaunch']['notify_mail'] = !empty($_POST['notify_mail']);
            $c['maintenance_text'] = $str('maintenance_text', 400);
            if ($c['mode'] === 'prelaunch') $c['launch_mailed'] = false;
            rs_save_config($c);
            flash($c['mode'] === 'prelaunch' && $c['launch_at'] === '' ? 'Gespeichert. Tipp: Ohne Startzeitpunkt zeigt die Vor-Launch-Seite keinen Countdown.' : 'Status gespeichert.', $c['mode'] === 'prelaunch' && $c['launch_at'] === '' ? 'warn' : 'ok');
            back('#status');
        case 'launch_now':
            $wasPre = $c['mode'] === 'prelaunch';
            $c['mode'] = 'live'; rs_save_config($c);
            $n = $wasPre ? rs_launch_notify() : 0;
            flash('Die Seite ist jetzt für alle online.' . ($n ? " $n Benachrichtigung(en) verschickt." : '')); back();
        case 'prelaunch_texts':
            foreach (['kicker' => 60, 'title' => 80, 'script' => 60, 'text' => 500] as $k => $max) $c['prelaunch'][$k] = $str('pl_' . $k, $max) ?: $c['prelaunch'][$k];
            $c['prelaunch']['signup'] = !empty($_POST['pl_signup']);
            rs_save_config($c); flash('Texte der Vor-Launch-Seite gespeichert.'); back('#vorlaunch');
        case 'add_code':
            $name = $str('name', 80);
            if ($name === '') { flash('Bitte einen Namen für den Zugang angeben.', 'error'); back('#zugaenge'); }
            $exp = trim((string)($_POST['expires'] ?? ''));
            rs_locked('codes', function () use ($name, $exp) {
                $codes = rs_codes();
                $codes[] = ['id' => bin2hex(random_bytes(6)), 'name' => $name, 'code' => rs_new_code(), 'created' => time(),
                            'expires' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp . 'T23:59' : '', 'active' => true, 'uses' => 0, 'last_used' => 0];
                rs_save_codes($codes);
            });
            flash('Zugang für „' . $name . '“ angelegt – Link kopieren und weitergeben.'); back('#zugaenge');
        case 'toggle_code':
        case 'delete_code':
            $id = (string)($_POST['id'] ?? '');
            rs_locked('codes', function () use ($action, $id) {
                $codes = rs_codes();
                if ($action === 'delete_code') $codes = array_filter($codes, fn($x) => $x['id'] !== $id);
                else foreach ($codes as &$x) if ($x['id'] === $id) $x['active'] = empty($x['active']);
                unset($x); rs_save_codes($codes);
            });
            flash($action === 'delete_code' ? 'Zugang gelöscht.' : 'Zugang aktualisiert.'); back('#zugaenge');
        case 'signup_delete':
            $em = (string)($_POST['email'] ?? '');
            rs_locked('signups', function () use ($em) { rs_write('signups', array_values(array_filter(rs_signups(), fn($x) => $x['email'] !== $em))); });
            flash('Eintrag gelöscht.'); back('#anmeldungen');
        case 'signup_export':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="reserved-anmeldungen-' . date('Y-m-d') . '.csv"');
            echo "\xEF\xBB\xBFE-Mail;Datum\n";
            foreach (rs_signups() as $x) echo '"' . str_replace('"', '""', $x['email']) . '";' . date('d.m.Y H:i', (int)$x['ts']) . "\n";
            exit;
        case 'angebot':
            $A = &$c['angebot'];
            $A['on'] = !empty($_POST['lp_on']);
            foreach (['kicker' => 90, 'title' => 120, 'script' => 60, 'lead' => 400, 'cta' => 50, 'offer_badge' => 24, 'offer_title' => 90, 'offer_text' => 300] as $k => $max) $A[$k] = $str('lp_' . $k, $max);
            $A['offer_on'] = !empty($_POST['lp_offer_on']);
            $u = trim((string)($_POST['lp_offer_until'] ?? '')); $A['offer_until'] = rs_ts($u) ? $u : '';
            $A['bullets'] = array_values(array_filter(array_map(fn($b) => rs_cut((string)$b, 90), (array)($_POST['lp_bullets'] ?? [])), fn($b) => $b !== ''));
            foreach (['results', 'steps', 'ig', 'plans', 'quotes', 'faq'] as $k) $A['show'][$k] = !empty($_POST['lp_show'][$k]);
            $A['quotes_n'] = max(1, min(9, (int)($_POST['lp_quotes_n'] ?? 3)));
            $A['faq'] = [];
            foreach ($list('faq') as $f) if (trim((string)($f['q'] ?? '')) !== '') $A['faq'][] = ['q' => rs_cut((string)$f['q'], 160), 'a' => rs_cut((string)($f['a'] ?? ''), 1200)];
            unset($A);
            if ($c['angebot']['offer_on'] && $c['angebot']['offer_title'] === '') flash('Gespeichert – für die Aktion bitte noch einen Titel eintragen, sonst wird sie nicht angezeigt.', 'warn');
            else flash('Landingpage gespeichert – sofort sichtbar.');
            rs_save_config($c); back('#landingpage');

        /* ----- Instagram ----- */
        case 'ig_save':
            $tok = trim((string)($_POST['token'] ?? ''));
            if ($tok !== '' && $tok !== str_repeat('•', 12)) {
                if (!preg_match('/^[A-Za-z0-9_\-|.]{20,}$/', $tok)) { flash('Der Zugangsschlüssel sieht nicht richtig aus – bitte komplett kopieren.', 'error'); back(); }
                $c['ig']['token'] = $tok; $c['ig']['token_expires'] = time() + 60 * 86400; $c['ig']['token_refreshed'] = 0; $c['ig']['last_sync'] = 0;
            }
            $c['ig']['on'] = !empty($_POST['on']);
            $c['ig']['count'] = in_array((int)($_POST['count'] ?? 9), [6, 9, 12, 15], true) ? (int)$_POST['count'] : 9;
            rs_save_config($c);
            if ($c['ig']['token'] !== '' && $c['ig']['on']) {
                $r = rs_ig_sync(true);
                flash($r['ok'] ? 'Instagram verbunden – ' . $r['count'] . ' Beiträge geladen.' : 'Gespeichert, aber Instagram meldet: ' . $r['error'], $r['ok'] ? 'ok' : 'error');
            } else flash('Gespeichert.');
            back();
        case 'ig_sync':
            $r = rs_ig_sync(true);
            flash($r['ok'] ? 'Aktualisiert – ' . $r['count'] . ' Beiträge.' : 'Fehler: ' . $r['error'], $r['ok'] ? 'ok' : 'error'); back();
        case 'ig_disconnect':
            $c['ig']['token'] = ''; $c['ig']['token_expires'] = 0; $c['ig']['last_error'] = '';
            rs_save_config($c); @unlink(RS_DATA . '/instagram.php');
            foreach (glob(rs_root() . '/' . RS_IG_DIR . '/*.jpg') ?: [] as $f) @unlink($f);
            flash('Instagram getrennt – die Webseite zeigt wieder die festen Beispielbilder.'); back();

        /* ----- Einstellungen ----- */
        case 'settings':
            $mail = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) { flash('Bitte eine gültige E-Mail-Adresse eintragen.', 'error'); back(); }
            $S['email'] = $mail;
            $from = trim((string)($_POST['mail_from'] ?? ''));
            $S['mail_from'] = $from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : $S['mail_from'];
            $S['instagram'] = ltrim(rs_cut((string)($_POST['instagram'] ?? ''), 60), '@') ?: $S['instagram'];
            foreach (['imp_name' => 80, 'imp_business' => 120, 'imp_address' => 300, 'imp_ustid' => 30, 'datenschutz' => 20000] as $k => $max) $S[$k] = $str($k, $max);
            rs_save_config($c); flash('Einstellungen gespeichert.'); back();
        case 'stats':
            $c['stats_on'] = !empty($_POST['stats_on']); rs_save_config($c);
            flash($c['stats_on'] ? 'Statistik eingeschaltet.' : 'Statistik ausgeschaltet – es wird nichts mehr gezählt.'); back();
        case 'stats_reset':
            rs_stats_reset(); flash('Statistik zurückgesetzt.'); back();
        case 'admin_pw':
            if (!password_verify((string)($_POST['old'] ?? ''), $c['admin_hash'])) flash('Aktuelles Passwort falsch.', 'error');
            elseif (strlen((string)($_POST['new'] ?? '')) < 10) flash('Das neue Passwort braucht mindestens 10 Zeichen.', 'error');
            else { $c['admin_hash'] = password_hash((string)$_POST['new'], PASSWORD_DEFAULT); rs_save_config($c); flash('Passwort geändert.'); }
            back();
        case 'test_mail':
            $ok = rs_mail($S['email'], 'Test-E-Mail von deiner Webseite', "Hallo!\n\nWenn du diese E-Mail liest, kommen Anfragen von der Webseite richtig bei dir an.\n\nReserved. Webseite");
            flash($ok ? 'Test-E-Mail an ' . $S['email'] . ' verschickt – bitte auch im Spam-Ordner nachsehen.' : 'Versand fehlgeschlagen. Beim Hoster prüfen, ob PHP-Mail aktiv ist, und eine Absender-Adresse der eigenen Domain eintragen.', $ok ? 'ok' : 'error');
            back();
    }
    back();
}

/* ==========================================================================
   Daten für die Ansicht
   ========================================================================== */
$flash = $_SESSION['rs_flash'] ?? null; unset($_SESSION['rs_flash']);
$csrf = rs_csrf();
$S = $c['site'];
$leads = array_reverse(rs_leads());
$newLeads = count(array_filter($leads, fn($x) => $x['status'] === 'neu'));
$range = in_array((int)($_GET['tage'] ?? 30), [7, 30, 90, 365], true) ? (int)($_GET['tage'] ?? 30) : 30;
$st = in_array($tab, ['start', 'statistik'], true) ? rs_stats_summary($range) : null;
$hidden = fn(string $action) => '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="tab" value="' . e($GLOBALS['tab']) . '">';
$n = fn($v, int $dec = 0) => number_format((float)$v, $dec, ',', '.');
$pct = fn($a, $b) => $b > 0 ? (int)round($a / $b * 100) : 0;
$trend = function (int $now, int $prev): string {
    if ($prev <= 0) return $now > 0 ? '<span class="trend up">neu</span>' : '';
    $p = (int)round(($now - $prev) / $prev * 100);
    return '<span class="trend ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '↑ ' : '↓ ') . abs($p) . ' %</span>';
};
$dur = fn(int $s) => $s >= 60 ? floor($s / 60) . ' Min. ' . ($s % 60) . ' Sek.' : $s . ' Sek.';
$igData = rs_read('instagram', []);
$igHist = rs_read('ig-history', []);

/** Balkenliste */
function bars(array $items, int $total = 0, int $limit = 8, ?callable $label = null, string $unit = ''): string {
    if (!$items || !array_sum($items)) return '<p class="empty">Noch keine Daten.</p>';
    $max = max($items); $total = $total ?: array_sum($items); $o = '';
    foreach (array_slice($items, 0, $limit, true) as $k => $v) {
        $o .= '<div class="row"><span class="fill" style="--w:' . round($v / max(1, $max) * 100) . '%"></span><span class="lbl">' . e($label ? $label((string)$k) : (string)$k) . '</span><b>' . number_format((float)$v, 0, ',', '.') . $unit . '</b><small>' . round($v / max(1, $total) * 100) . ' %</small></div>';
    }
    return $o;
}
/** Kluge Hinweise aus den Zahlen */
function insights(array $st, array $leads, array $igHist, int $range): array {
    $o = [];
    $src = array_diff_key($st['r'], ['direkt' => 1]);
    if ($src) { $top = array_key_first($src); $o[] = ['src', "<b>" . e($top) . "</b> bringt die meisten Besucher (" . round($src[$top] / max(1, array_sum($st['r'])) * 100) . ' %).'];}
    if (array_sum($st['h']) >= 10) {
        $h = array_search(max($st['h']), $st['h'], true); $wdn = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $wd = array_search(max($st['wd']), $st['wd'], true);
        $o[] = ['time', "Die meisten Besuche: <b>{$wdn[$wd]}s</b> gegen <b>{$h}–" . ($h + 1) . " Uhr</b> – guter Zeitpunkt für neue Posts."];
    }
    $mob = $st['g']['m'] ?? 0; $all = array_sum($st['g']);
    if ($all >= 5) $o[] = ['phone', '<b>' . round($mob / $all * 100) . ' %</b> der Besucher sind am Handy unterwegs.'];
    $ia = $st['br']['Instagram-App'] ?? 0;
    if ($ia > 0) $o[] = ['ig', "<b>$ia</b> Besucher kamen direkt aus der Instagram-App (Link in Bio, Story oder DM)."];
    $plans = array_filter($st['c'], fn($k) => str_starts_with((string)$k, 'plan-'), ARRAY_FILTER_USE_KEY);
    if ($plans) { arsort($plans); $k = (int)substr((string)array_key_first($plans), 5) - 1; $o[] = ['plan', 'Meistgefragtes Paket auf der Seite: <b>' . e(rs_config()['site']['plans'][$k]['name'] ?? '') . '</b>.']; }
    $fs = $st['c']['form-start'] ?? 0;
    if ($fs >= 3) $o[] = ['form', "<b>{$fs}×</b> wurde das Formular begonnen, <b>{$st['a']}×</b> abgeschickt (" . round($st['a'] / $fs * 100) . ' %).'];
    if (count($igHist) >= 2) {
        $vals = array_filter(array_column($igHist, 'f'), fn($v) => $v !== null);
        if (count($vals) >= 2) { $diff = end($vals) - reset($vals); $o[] = ['ig', 'Instagram: ' . ($diff >= 0 ? '+' : '') . $diff . ' Follower seit ' . date('d.m.', strtotime((string)array_key_first($igHist)))]; }
    }
    $hot = array_filter($leads, fn($x) => $x['status'] === 'neu' && $x['score'] >= 65);
    if ($hot) array_unshift($o, ['hot', '<b>' . count($hot) . ' heiße Anfrage' . (count($hot) > 1 ? 'n' : '') . '</b> warten auf Antwort – am besten heute melden.']);
    return $o;
}
function lead_wa(string $tel): string {
    $d = (string)preg_replace('/\D+/', '', str_replace('(0)', '', $tel));
    if (str_starts_with($d, '00')) $d = substr($d, 2); elseif (str_starts_with($d, '0')) $d = '49' . substr($d, 1);
    return $d;
}

head('Admin – Reserved.', true);
?>
<header class="top">
  <a class="brand" href="?tab=start">Reserved<span>.</span> <small>Admin</small></a>
  <nav class="tabs" aria-label="Bereiche">
    <?php foreach ($tabs as $k => $t): ?><a href="?tab=<?= $k ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= icon($k) ?><span><?= $t ?></span><?= $k === 'anfragen' && $newLeads ? '<i class="badge-n">' . $newLeads . '</i>' : '' ?></a><?php endforeach; ?>
  </nav>
  <div class="top-r"><a href="<?= e(rs_url('')) ?>" target="_blank" rel="noopener" class="ghost">Seite ansehen ↗</a><a href="?logout" class="ghost">Abmelden</a></div>
</header>
<main class="wrap">
<?php if ($flash): ?><p class="flash <?= e($flash[1]) ?>" role="status"><?= e($flash[0]) ?></p><?php endif; ?>
<?php $effMode = rs_effective_mode($c); if ($effMode === 'maintenance'): ?><p class="flash warn">Wartungsmodus ist an – Besucher sehen gerade nur die Wartungsseite. <a href="?tab=seiten">Ändern</a></p>
<?php elseif ($effMode === 'prelaunch'): ?><p class="flash warn">Vor-Launch aktiv – Besucher sehen die Countdown-Seite<?= rs_ts($c['launch_at']) ? ' bis ' . e(rs_date_de((int)rs_ts($c['launch_at']))) : '' ?>. <a href="?tab=seiten">Ändern</a></p><?php endif; ?>

<?php /* ======================= ÜBERSICHT ======================= */ if ($tab === 'start'):
  $maxDay = max(1, ...array_map(fn($d) => $d['u'], $st['days']));
  $conv = $st['u'] ? $st['a'] / $st['u'] * 100 : 0;
  $hello = (int)date('G') < 11 ? 'Guten Morgen' : ((int)date('G') < 18 ? 'Hallo' : 'Guten Abend');
?>
  <div class="page-head">
    <div><h1><?= $hello ?>, <?= e(explode(' ', $S['imp_name'])[0]) ?>.</h1><p class="sub">So läuft deine Webseite in den letzten <?= $range === 365 ? '12 Monaten' : $range . ' Tagen' ?>.</p></div>
    <?= range_nav($range, 'start') ?>
  </div>
  <section class="kpis">
    <div class="kpi"><span>Besucher</span><b><?= $n($st['u']) ?></b><?= $trend($st['u'], $st['prev']['u']) ?></div>
    <div class="kpi"><span>Anfragen</span><b><?= $n($st['a']) ?></b><?= $trend($st['a'], $st['prev']['a']) ?></div>
    <div class="kpi"><span>Anfrage-Quote</span><b><?= $n($conv, 1) ?> %</b><small>Besucher → Anfrage</small></div>
    <div class="kpi"><span>Ø Verweildauer</span><b><?= $st['v'] ? $dur((int)round($st['t'] / $st['v'])) : '–' ?></b><small><?= $pct($st['e'], $st['v']) ?> % bleiben über 15 Sek.</small></div>
  </section>

  <div class="grid g-2-1">
    <section class="card">
      <div class="card-h"><h2>Besucher pro Tag</h2><span class="legend"><i class="lg-a"></i> Tag mit Anfrage</span></div>
      <div class="chart" role="img" aria-label="Besucher pro Tag">
        <?php foreach ($st['days'] as $d): $h = $d['u'] ? max(3, round($d['u'] / $maxDay * 100)) : 0; ?><span class="cbar<?= $d['a'] ? ' has-a' : '' ?>" style="--h:<?= $h ?>%" data-tip="<?= e(date('d.m.', strtotime($d['day']))) ?> · <?= $d['u'] ?> Besucher<?= $d['a'] ? ' · ' . $d['a'] . ' Anfrage(n)' : '' ?>"><i></i></span><?php endforeach; ?>
      </div>
      <div class="chart-axis"><span><?= e(date('d.m.', strtotime($st['days'][0]['day']))) ?></span><span>heute</span></div>
    </section>
    <section class="card">
      <h2>Auf einen Blick</h2>
      <?php $ins = insights($st, $leads, $igHist, $range); if (!$ins): ?><p class="empty">Sobald Besucher kommen, erscheinen hier Hinweise.</p><?php endif; ?>
      <ul class="insights"><?php foreach ($ins as [$k, $t]): ?><li class="in-<?= e($k) ?>"><?= $t ?></li><?php endforeach; ?></ul>
    </section>
  </div>

  <div class="grid g-1-1">
    <section class="card">
      <div class="card-h"><h2>Neueste Anfragen</h2><a href="?tab=anfragen" class="more">Alle ansehen →</a></div>
      <?php if (!$leads): ?><p class="empty">Noch keine Anfragen – sobald jemand das Formular abschickt, steht sie hier und du bekommst eine E-Mail.</p><?php endif; ?>
      <ul class="leads compact"><?php foreach (array_slice($leads, 0, 5) as $x) echo lead_row($x); ?></ul>
    </section>
    <section class="card">
      <div class="card-h"><h2>Woher kommen die Besucher?</h2><a href="?tab=statistik" class="more">Details →</a></div>
      <div class="bars"><?= bars($st['r'], 0, 6, fn($k) => $k === 'direkt' ? 'Direkt / Lesezeichen' : $k) ?></div>
    </section>
  </div>

<?php /* ======================= ANFRAGEN ======================= */ elseif ($tab === 'anfragen'):
  $o = rs_lead_options(); $stat = rs_lead_status();
  $id = (string)($_GET['id'] ?? ''); $one = null;
  foreach ($leads as $x) if ($x['id'] === $id) $one = $x;
  if ($one && empty($one['seen'])) rs_locked('leads', function () use ($id) { $l = rs_leads(); foreach ($l as &$x) if ($x['id'] === $id) $x['seen'] = true; unset($x); rs_save_leads($l); });
  if ($one): $d = $one['data']; [$tk, $tl] = rs_lead_temp($one['score']); $first = explode(' ', $d['name'])[0];
    $mailBody = "Hallo $first,\n\nvielen Dank für deine Anfrage bei Reserved.!\n\n";
    $mailto = 'mailto:' . rawurlencode($d['email']) . '?subject=' . rawurlencode('Deine Anfrage bei Reserved.') . '&body=' . rawurlencode($mailBody);
?>
  <a href="?tab=anfragen" class="backlink">← Alle Anfragen</a>
  <div class="page-head">
    <div><h1><?= e($d['firma'] ?: $d['name']) ?></h1><p class="sub"><?= e(rs_date_de($one['created'], true)) ?> · über <?= e($one['src']) ?> · <?= e($one['device']) ?></p></div>
    <div class="head-tags"><span class="temp t-<?= $tk ?>"><?= $tl ?> · <?= (int)$one['score'] ?></span><span class="pill-s st-<?= e($one['status']) ?>"><?= e($stat[$one['status']] ?? '') ?></span></div>
  </div>
  <div class="grid g-2-1">
    <section class="card">
      <div class="quick">
        <a class="btn" href="<?= e($mailto) ?>">✉️ Per E-Mail antworten</a>
        <?php if ($d['telefon']): ?><a class="btn btn-ghost" href="tel:<?= e(preg_replace('/[^\d+]/', '', $d['telefon'])) ?>">📞 Anrufen</a>
        <a class="btn btn-ghost" href="https://wa.me/<?= e(lead_wa($d['telefon'])) ?>?text=<?= rawurlencode("Hallo $first, hier ist Victoria von Reserved. – danke für deine Anfrage!") ?>" target="_blank" rel="noopener">💬 WhatsApp</a><?php endif; ?>
        <?php if ($d['insta']): ?><a class="btn btn-ghost" href="https://www.instagram.com/<?= e(rawurlencode($d['insta'])) ?>/" target="_blank" rel="noopener">📷 @<?= e($d['insta']) ?></a><?php endif; ?>
      </div>
      <div class="rec-box"><span>Empfehlung der Webseite</span><b><?= e($one['rec']) ?></b><small><?= e($one['why']) ?></small></div>
      <dl class="facts">
        <dt>Name</dt><dd><?= e($d['name']) ?></dd>
        <dt>E-Mail</dt><dd><a href="mailto:<?= e($d['email']) ?>"><?= e($d['email']) ?></a></dd>
        <dt>Telefon</dt><dd><?= e($d['telefon'] ?: '–') ?></dd>
        <dt>Am liebsten per</dt><dd><strong><?= e($o['kontakt'][$d['kontakt']] ?? '–') ?></strong></dd>
        <dt>Unternehmen</dt><dd><?= e($d['firma'] ?: '–') ?></dd>
        <dt>Branche</dt><dd><?= e($d['branche'] === 'andere' && $d['branche_text'] ? $d['branche_text'] : ($o['branche'][$d['branche']] ?? '–')) ?></dd>
        <dt>Ort</dt><dd><?= e($d['ort'] ?: '–') ?></dd>
        <dt>Ziele</dt><dd><?= $d['ziele'] ? implode('', array_map(fn($z) => '<span class="chip-s">' . e($o['ziele'][$z]) . '</span>', $d['ziele'])) : '–' ?></dd>
        <dt>Social Media</dt><dd><?= e($o['stand'][$d['stand']] ?? '–') ?></dd>
        <dt>Umfang</dt><dd><?= e($o['umfang'][$d['umfang']] ?? '–') ?></dd>
        <dt>Start</dt><dd><?= e($o['start'][$d['start']] ?? '–') ?></dd>
        <?php if ($d['paket']): ?><dt>Paket geklickt</dt><dd><?= e($d['paket']) ?></dd><?php endif; ?>
        <?php if (($d['follower'] ?? '') !== ''): ?><dt>Follower</dt><dd><?= e($o['follower'][$d['follower']] ?? '') ?></dd><?php endif; ?>
        <?php if (($d['posting'] ?? '') !== ''): ?><dt>Postet aktuell</dt><dd><?= e($o['posting'][$d['posting']] ?? '') ?></dd><?php endif; ?>
        <?php if (($d['budget'] ?? '') !== ''): ?><dt>Budget</dt><dd><strong><?= e($d['budget']) ?></strong></dd><?php endif; ?>
        <dt>Seite</dt><dd><?= ['angebot' => '<span class="chip-s">Landingpage /angebot</span>', 'check' => '<span class="chip-s">Fit-Check /start</span>'][$one['page'] ?? ''] ?? 'Startseite' ?></dd>
      </dl>
      <?php if ($d['nachricht']): ?><h3>Nachricht</h3><p class="msg"><?= nl2br(e($d['nachricht'])) ?></p><?php endif; ?>
    </section>
    <aside class="card">
      <h2>Status</h2>
      <form method="post" class="status-pick"><?= $hidden('lead_status') ?><input type="hidden" name="id" value="<?= e($one['id']) ?>">
        <?php foreach ($stat as $k => $t): ?><button name="status" value="<?= $k ?>" class="st st-<?= $k ?><?= $one['status'] === $k ? ' on' : '' ?>"><?= e($t) ?></button><?php endforeach; ?>
      </form>
      <h2 class="mt">Notizen</h2>
      <form method="post" class="stack"><?= $hidden('lead_note') ?><input type="hidden" name="id" value="<?= e($one['id']) ?>">
        <textarea name="note" rows="3" placeholder="z. B. Am Freitag angerufen, Termin am 12.11. um 10 Uhr"></textarea><button class="btn btn-small">Notiz speichern</button></form>
      <ul class="notes"><?php foreach (array_reverse($one['notes']) as $nt): ?><li class="<?= !empty($nt['sys']) ? 'sys' : '' ?>"><small><?= e(date('d.m.Y H:i', $nt['ts'])) ?></small><?= nl2br(e($nt['t'])) ?></li><?php endforeach; ?>
        <li class="sys"><small><?= e(date('d.m.Y H:i', $one['created'])) ?></small>Anfrage eingegangen</li></ul>
      <form method="post" class="mt" onsubmit="return confirm('Anfrage endgültig löschen?')"><?= $hidden('lead_delete') ?><input type="hidden" name="id" value="<?= e($one['id']) ?>"><button class="btn btn-small btn-danger">Anfrage löschen</button></form>
    </aside>
  </div>
<?php else:
  $f = (string)($_GET['status'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
  $counts = array_count_values(array_column($leads, 'status'));
  $shown = array_filter($leads, fn($x) => ($f === '' || $x['status'] === $f) && ($q === '' || stripos(implode(' ', [$x['data']['name'], $x['data']['firma'], $x['data']['email'], $x['data']['ort']]), $q) !== false));
  $won = $counts['gewonnen'] ?? 0; $closed = $won + ($counts['verloren'] ?? 0);
?>
  <div class="page-head"><div><h1>Anfragen</h1><p class="sub"><?= count($leads) ?> insgesamt · <?= $newLeads ?> neu<?= $closed ? ' · ' . round($won / $closed * 100) . ' % gewonnen' : '' ?></p></div>
    <form method="post"><?= $hidden('lead_export') ?><button class="btn btn-ghost btn-small"<?= $leads ? '' : ' disabled' ?>>Als Excel/CSV laden</button></form></div>
  <div class="pipeline">
    <a href="?tab=anfragen" class="<?= $f === '' ? 'on' : '' ?>"><b><?= count($leads) ?></b>Alle</a>
    <?php foreach (rs_lead_status() as $k => $t): ?><a href="?tab=anfragen&status=<?= $k ?>" class="st-<?= $k ?><?= $f === $k ? ' on' : '' ?>"><b><?= $counts[$k] ?? 0 ?></b><?= e($t) ?></a><?php endforeach; ?>
  </div>
  <form class="search" method="get"><input type="hidden" name="tab" value="anfragen"><?php if ($f): ?><input type="hidden" name="status" value="<?= e($f) ?>"><?php endif; ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Suchen nach Name, Firma, Ort …"></form>
  <section class="card flush">
    <?php if (!$shown): ?><p class="empty pad">Keine Anfragen<?= $f || $q ? ' für diese Auswahl' : '' ?>.</p><?php endif; ?>
    <ul class="leads"><?php foreach ($shown as $x) echo lead_row($x); ?></ul>
  </section>
  <section class="card">
    <h2>Einstellungen für Anfragen</h2>
    <form method="post" class="row-form"><?= $hidden('lead_settings') ?>
      <label class="check"><input type="checkbox" name="autoreply" value="1"<?= !empty($c['leads']['autoreply']) ? ' checked' : '' ?>> Automatische Bestätigung an die Kund:in senden (mit Paket-Empfehlung)</label>
      <label class="inline">Anfragen löschen nach <input type="number" name="keep_months" min="0" max="60" value="<?= (int)$c['leads']['keep_months'] ?>"> Monaten <small>(0 = nie; „Kunde geworden“ bleibt)</small></label>
      <button class="btn btn-small">Speichern</button>
    </form>
  </section>
<?php endif; ?>

<?php /* ======================= STATISTIK ======================= */ elseif ($tab === 'statistik'):
  $sections = rs_stats_sections(); $clicks = rs_stats_clicks();
  $hMax = max(1, ...$st['h']); $wdMax = max(1, ...$st['wd']);
  $funnel = ['Formular begonnen' => $st['c']['form-start'] ?? 0, 'Schritt 2 (Ziele)' => $st['c']['form-step2'] ?? 0, 'Schritt 3 (Kontakt)' => $st['c']['form-step3'] ?? 0, 'Anfrage gesendet' => $st['a']];
?>
  <div class="page-head"><div><h1>Statistik</h1><p class="sub">Ohne Cookies, keine IP-Adressen gespeichert. Du selbst wirst nicht mitgezählt.</p></div><?= range_nav($range, 'statistik') ?></div>
  <?php if (empty($c['stats_on'])): ?><p class="flash warn">Die Statistik ist ausgeschaltet (Einstellungen).</p><?php endif; ?>
  <section class="kpis k6">
    <div class="kpi"><span>Besucher</span><b><?= $n($st['u']) ?></b><?= $trend($st['u'], $st['prev']['u']) ?></div>
    <div class="kpi"><span>Seitenaufrufe</span><b><?= $n($st['v']) ?></b><?= $trend($st['v'], $st['prev']['v']) ?></div>
    <div class="kpi"><span>Anfragen</span><b><?= $n($st['a']) ?></b><?= $trend($st['a'], $st['prev']['a']) ?></div>
    <div class="kpi"><span>Anfrage-Quote</span><b><?= $st['u'] ? $n($st['a'] / $st['u'] * 100, 1) : '0' ?> %</b></div>
    <div class="kpi"><span>Ø Verweildauer</span><b><?= $st['v'] ? $dur((int)round($st['t'] / $st['v'])) : '–' ?></b></div>
    <div class="kpi"><span>Echtes Interesse</span><b><?= $pct($st['e'], $st['v']) ?> %</b><small>länger als 15 Sek.</small></div>
  </section>

  <div class="grid g-1-1">
    <section class="card"><h2>Wie weit wird gescrollt?</h2><p class="hint">Anteil der Besuche, die den Abschnitt gesehen haben.</p>
      <div class="funnel"><?php foreach ($sections as $k => $t): $p = $pct($st['s'][$k] ?? 0, $st['v']); ?><div class="frow"><span><?= e($t) ?></span><i style="--w:<?= min(100, $p) ?>%"></i><b><?= min(100, $p) ?> %</b></div><?php endforeach; ?></div></section>
    <section class="card"><h2>Weg zur Anfrage</h2><p class="hint">Wo springen Interessenten im Formular ab?</p>
      <div class="funnel"><?php $f0 = max(1, reset($funnel)); foreach ($funnel as $t => $v): ?><div class="frow"><span><?= e($t) ?></span><i style="--w:<?= min(100, round($v / $f0 * 100)) ?>%"></i><b><?= $n($v) ?></b></div><?php endforeach; ?></div></section>
  </div>
  <div class="grid g-3">
    <section class="card"><h2>Herkunft</h2><div class="bars"><?= bars($st['r'], 0, 10, fn($k) => $k === 'direkt' ? 'Direkt / Lesezeichen' : $k) ?></div>
      <?php if ($st['cp']): ?><h3 class="mt">Kampagnen <small>(utm_campaign)</small></h3><div class="bars"><?= bars($st['cp'], 0, 6) ?></div><?php endif; ?>
      <h3 class="mt">Seiten</h3><div class="bars"><?= bars($st['p'], 0, 6, fn($k) => rs_stats_pages()[$k] ?? $k) ?></div></section>
    <section class="card"><h2>Klicks</h2><div class="bars"><?= bars($st['c'], max(1, $st['v']), 12, fn($k) => $clicks[$k] ?? $k) ?></div></section>
    <section class="card"><h2>Geräte</h2><div class="bars"><?= bars(array_filter(['Handy' => $st['g']['m'] ?? 0, 'Computer' => $st['g']['d'] ?? 0, 'Tablet' => $st['g']['t'] ?? 0])) ?></div>
      <h3 class="mt">Systeme</h3><div class="bars"><?= bars($st['os'], 0, 6) ?></div>
      <h3 class="mt">Browser &amp; Apps</h3><div class="bars"><?= bars($st['br'], 0, 7) ?></div></section>
  </div>
  <div class="grid g-1-1">
    <section class="card"><h2>Uhrzeit</h2><p class="hint">Seitenaufrufe nach Tageszeit – hilft beim Timing deiner Posts.</p>
      <div class="hours"><?php foreach ($st['h'] as $h => $v): ?><span style="--h:<?= round($v / $hMax * 100) ?>%" data-tip="<?= $h ?>–<?= $h + 1 ?> Uhr · <?= $v ?>"><i></i><small><?= $h % 3 === 0 ? $h : '' ?></small></span><?php endforeach; ?></div></section>
    <section class="card"><h2>Wochentage</h2>
      <div class="hours wd"><?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $i => $t): ?><span style="--h:<?= round($st['wd'][$i] / $wdMax * 100) ?>%" data-tip="<?= $t ?> · <?= $st['wd'][$i] ?>"><i></i><small><?= $t ?></small></span><?php endforeach; ?></div></section>
  </div>
  <?php if ($igHist):
    $hv = array_filter(array_map(fn($x) => $x['f'], $igHist), fn($v) => $v !== null);
    $last = end($igHist);
    $eng = $last && $last['f'] && $last['n'] ? $last['i'] / $last['n'] / $last['f'] * 100 : null;
    $top = $igData['posts'] ?? []; usort($top, fn($a, $b) => (($b['likes'] ?? 0) + ($b['comments'] ?? 0)) <=> (($a['likes'] ?? 0) + ($a['comments'] ?? 0)));
  ?>
  <section class="card">
    <div class="card-h"><h2>Instagram</h2><a href="?tab=instagram" class="more">Verbindung →</a></div>
    <div class="grid g-2-1 inner">
      <div><p class="hint">Follower-Entwicklung (täglich gespeichert)</p><?= spark($hv) ?></div>
      <div class="kpis mini">
        <div class="kpi"><span>Follower</span><b><?= $last['f'] !== null ? $n($last['f']) : '–' ?></b><?php if (count($hv) > 1): $dlt = end($hv) - reset($hv); ?><span class="trend <?= $dlt >= 0 ? 'up' : 'down' ?>"><?= $dlt >= 0 ? '+' : '' ?><?= $dlt ?></span><?php endif; ?></div>
        <div class="kpi"><span>Interaktionsrate</span><b><?= $eng !== null ? $n($eng, 1) . ' %' : '–' ?></b><small>Likes + Kommentare je Post / Follower</small></div>
      </div>
    </div>
    <?php if ($top): ?><h3 class="mt">Beliebteste Beiträge</h3><div class="topposts"><?php foreach (array_slice($top, 0, 4) as $p): ?><a href="<?= e($p['permalink']) ?>" target="_blank" rel="noopener"><img src="<?= e(rs_url($p['img'])) ?>" alt=""><span>♥ <?= (int)($p['likes'] ?? 0) ?> · 💬 <?= (int)($p['comments'] ?? 0) ?></span></a><?php endforeach; ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

<?php /* ======================= INHALTE ======================= */ elseif ($tab === 'inhalte'): ?>
  <div class="page-head"><div><h1>Inhalte</h1><p class="sub">Änderungen sind nach dem Speichern sofort auf der Webseite sichtbar. <b>**fett**</b> schreibt Text fett.</p></div></div>
  <nav class="subnav"><a href="#hero">Texte</a><a href="#pakete">Pakete</a><a href="#feedback">Feedback</a><a href="#ueber">Bild</a></nav>

  <section class="card" id="hero">
    <h2>Texte &amp; Hinweise</h2>
    <form method="post" class="form"><?= $hidden('content_hero') ?>
      <fieldset><legend>Startbereich</legend>
        <label>Zeile über der Überschrift<input name="hero_kicker" value="<?= e($S['hero_kicker']) ?>" maxlength="90"></label>
        <div class="cols"><label>Überschrift<input name="hero_title" value="<?= e($S['hero_title']) ?>" maxlength="120"></label><label>Schreibschrift-Zeile<input name="hero_script" value="<?= e($S['hero_script']) ?>" maxlength="60"></label></div>
        <label>Text<textarea name="hero_lead" rows="3" maxlength="400"><?= e($S['hero_lead']) ?></textarea></label>
        <div class="cols c3"><?php foreach ([0, 1, 2] as $i): $p = $S['proof'][$i] ?? ['v' => '', 't' => '']; ?><div class="mini-f"><label>Kennzahl <?= $i + 1 ?><input name="proof[<?= $i ?>][v]" value="<?= e($p['v']) ?>" maxlength="12" placeholder="z. B. 5×"></label><label>Beschreibung<input name="proof[<?= $i ?>][t]" value="<?= e($p['t']) ?>" maxlength="60"></label></div><?php endforeach; ?></div>
      </fieldset>
      <fieldset><legend>Ankündigungsleiste <?= !empty($S['bar_on']) && $S['bar_text'] ? '<span class="tag on">sichtbar</span>' : '<span class="tag">aus</span>' ?></legend>
        <label class="check"><input type="checkbox" name="bar_on" value="1"<?= !empty($S['bar_on']) ? ' checked' : '' ?>> Leiste ganz oben anzeigen</label>
        <label>Text<input name="bar_text" value="<?= e($S['bar_text']) ?>" maxlength="180" placeholder="z. B. Noch 2 Plätze für Dezember frei"></label>
        <div class="cols"><label>Link-Text<input name="bar_link_text" value="<?= e($S['bar_link_text']) ?>" maxlength="40" placeholder="Jetzt anfragen"></label><label>Link-Ziel<input name="bar_link" value="<?= e($S['bar_link']) ?>" maxlength="300" placeholder="#kontakt"></label></div>
      </fieldset>
      <fieldset><legend>Über uns &amp; Kontakt</legend>
        <label>Über uns – erster Absatz<textarea name="about_lead" rows="2" maxlength="400"><?= e($S['about_lead']) ?></textarea></label>
        <label>Über uns – zweiter Absatz<textarea name="about_text" rows="4" maxlength="1200"><?= e($S['about_text']) ?></textarea></label>
        <label>Antwortzeit (Kontakt &amp; Bestätigungs-Mail)<input name="response_text" value="<?= e($S['response_text']) ?>" maxlength="160"></label>
      </fieldset>
      <button class="btn">Texte speichern</button>
    </form>
  </section>

  <section class="card" id="pakete">
    <h2>Pakete</h2>
    <form method="post" class="form"><?= $hidden('content_plans') ?>
      <p class="hint">In die Zellen: <b>✓</b> (oder „ja“) für enthalten, <b>—</b> (oder leer) für nicht enthalten, sonst freier Text wie „bis zu 2“. Die mittlere Spalte ist dunkel hervorgehoben.</p>
      <div class="ptable">
        <div class="pt-head"><span></span><?php foreach ([0, 1, 2] as $i): $p = $S['plans'][$i]; ?><div><input name="plans[<?= $i ?>][tier]" value="<?= e($p['tier']) ?>" placeholder="Etikett" maxlength="30" class="sm"><input name="plans[<?= $i ?>][name]" value="<?= e($p['name']) ?>" placeholder="Name" maxlength="30" class="strong"><input name="plans[<?= $i ?>][price]" value="<?= e($p['price']) ?>" placeholder="Preis (optional), z. B. ab 590 € / Monat" maxlength="40" class="sm"></div><?php endforeach; ?><span></span></div>
        <div id="rows"><?php foreach ($S['plan_rows'] as $i => $r): ?><div class="pt-row"><input name="rows[<?= $i ?>][l]" value="<?= e($r['l']) ?>" maxlength="80"><?php foreach ([0, 1, 2] as $k): ?><input name="rows[<?= $i ?>][v][<?= $k ?>]" value="<?= e($r['v'][$k] ?? '') ?>" maxlength="30" class="c"><?php endforeach; ?><button type="button" class="x" data-remove aria-label="Zeile löschen">✕</button></div><?php endforeach; ?></div>
        <template id="tpl-row"><div class="pt-row"><input name="rows[__][l]" placeholder="Leistung" maxlength="80"><input name="rows[__][v][0]" class="c" maxlength="30"><input name="rows[__][v][1]" class="c" maxlength="30"><input name="rows[__][v][2]" class="c" maxlength="30"><button type="button" class="x" data-remove aria-label="Zeile löschen">✕</button></div></template>
        <button type="button" class="btn btn-ghost btn-small" data-add="tpl-row" data-to="rows">+ Zeile</button>
      </div>
      <label>Hinweis unter der Tabelle<input name="plans_note" value="<?= e($S['plans_note']) ?>" maxlength="160"></label>
      <fieldset><legend>Zusatzleistungen</legend>
        <div id="extras"><?php foreach ($S['extras'] as $i => $x): ?><div class="lrow"><input name="extras[<?= $i ?>][t]" value="<?= e($x['t']) ?>" placeholder="Titel" maxlength="60"><input name="extras[<?= $i ?>][d]" value="<?= e($x['d']) ?>" placeholder="Beschreibung" maxlength="160" class="grow"><input name="extras[<?= $i ?>][p]" value="<?= e($x['p']) ?>" placeholder="Preis (optional)" maxlength="40"><button type="button" class="x" data-remove aria-label="Löschen">✕</button></div><?php endforeach; ?></div>
        <template id="tpl-extra"><div class="lrow"><input name="extras[__][t]" placeholder="Titel" maxlength="60"><input name="extras[__][d]" placeholder="Beschreibung" maxlength="160" class="grow"><input name="extras[__][p]" placeholder="Preis (optional)" maxlength="40"><button type="button" class="x" data-remove aria-label="Löschen">✕</button></div></template>
        <button type="button" class="btn btn-ghost btn-small" data-add="tpl-extra" data-to="extras">+ Zusatzleistung</button>
      </fieldset>
      <button class="btn">Pakete speichern</button>
    </form>
  </section>

  <section class="card" id="feedback">
    <h2>Feedback</h2>
    <form method="post" class="form"><?= $hidden('content_feedback') ?>
      <fieldset><legend>Ergebnis-Karten</legend>
        <div class="cols c3"><?php foreach ([0, 1, 2] as $i): $r = $S['results'][$i] ?? ['v' => '', 't' => '', 'l' => '']; ?><div class="mini-f"><label>Groß<input name="results[<?= $i ?>][v]" value="<?= e($r['v']) ?>" maxlength="20"></label><label>Text<textarea name="results[<?= $i ?>][t]" rows="3" maxlength="200"><?= e($r['t']) ?></textarea></label><label>Kunde<input name="results[<?= $i ?>][l]" value="<?= e($r['l']) ?>" maxlength="40"></label></div><?php endforeach; ?></div>
      </fieldset>
      <fieldset><legend>Stimmen <small>(<?= count($S['quotes']) ?>)</small></legend>
        <p class="hint">Neue Stimmen erscheinen oben. Kunden mit gleichem Namen bekommen automatisch einen Filter-Button.</p>
        <button type="button" class="btn btn-ghost btn-small" data-add="tpl-quote" data-to="quotes" data-prepend>+ Neue Stimme</button>
        <div id="quotes" class="qlist"><?php foreach ($S['quotes'] as $i => $q) echo quote_editor((string)$i, $q); ?></div>
        <template id="tpl-quote"><?= quote_editor('__', ['t' => '', 'n' => '', 'c' => '', 'note' => '', 'blue' => false, 'on' => true]) ?></template>
      </fieldset>
      <button class="btn">Feedback speichern</button>
    </form>
  </section>

  <section class="card" id="ueber">
    <h2>Bild „Über uns“</h2>
    <div class="img-slot"><img src="<?= e(rs_url($S['img_about'] ?: 'assets/img/post-dm.webp')) ?>" alt="">
      <div><p class="hint">Hochformat (4:5), JPG/PNG/WebP. Ersetzt das Bild sofort.</p>
        <form method="post" enctype="multipart/form-data"><?= $hidden('image') ?><label class="btn btn-ghost btn-small file-btn">Neues Bild wählen<input type="file" name="file" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"></label></form>
        <?php if ($S['img_about']): ?><form method="post" onsubmit="return confirm('Eigenes Bild entfernen?')"><?= $hidden('image_delete') ?><button class="btn btn-small btn-danger">Entfernen</button></form><?php endif; ?></div></div>
  </section>

<?php /* ======================= INSTAGRAM ======================= */ elseif ($tab === 'instagram'):
  $ig = $c['ig']; $connected = $ig['token'] !== ''; $P = $igData['profile'] ?? null;
  $daysLeft = $ig['token_expires'] ? (int)floor(($ig['token_expires'] - time()) / 86400) : null;
?>
  <div class="page-head"><div><h1>Instagram</h1><p class="sub">Die Webseite zeigt automatisch deine neuesten Beiträge – mit Followern, Likes und Kommentaren.</p></div></div>
  <div class="grid g-1-1">
    <section class="card">
      <h2>Verbindung</h2>
      <?php if ($connected && $P): ?>
        <div class="ig-conn"><?php if ($P['pic']): ?><img src="<?= e(rs_url($P['pic'])) ?>" alt=""><?php endif; ?><div><b>@<?= e($P['username']) ?></b><small><?= $P['followers'] !== null ? $n($P['followers']) . ' Follower · ' : '' ?><?= (int)$P['media'] ?> Beiträge</small></div><span class="tag on">verbunden</span></div>
      <?php elseif ($connected): ?><p class="flash warn">Schlüssel eingetragen, aber noch keine Daten geladen.</p><?php endif; ?>
      <?php if ($ig['last_error']): ?><p class="flash error"><?= e($ig['last_error']) ?></p><?php endif; ?>
      <?php if ($connected): ?><p class="hint">Zuletzt aktualisiert: <?= $ig['last_sync'] ? e(rs_ago((int)$ig['last_sync'])) : 'noch nie' ?> · aktualisiert sich alle 30 Minuten selbst<?= $daysLeft !== null ? ' · Schlüssel gültig noch ca. ' . max(0, $daysLeft) . ' Tage (wird automatisch verlängert)' : '' ?></p><?php endif; ?>
      <form method="post" class="form"><?= $hidden('ig_save') ?>
        <label>Zugangsschlüssel (Access Token)<input name="token" value="<?= $connected ? str_repeat('•', 12) : '' ?>" placeholder="IGAA…" autocomplete="off" spellcheck="false" onfocus="if(this.value.startsWith('•'))this.select()"></label>
        <div class="cols"><label>Anzahl Beiträge auf der Seite<select name="count"><?php foreach ([6, 9, 12, 15] as $k): ?><option<?= (int)$ig['count'] === $k ? ' selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></label>
          <label class="check self-end"><input type="checkbox" name="on" value="1"<?= $ig['on'] ? ' checked' : '' ?>> Live-Feed auf der Webseite zeigen</label></div>
        <div class="btnrow"><button class="btn">Speichern &amp; laden</button></div>
      </form>
      <?php if ($connected): ?><div class="btnrow mt">
        <form method="post"><?= $hidden('ig_sync') ?><button class="btn btn-ghost btn-small">Jetzt aktualisieren</button></form>
        <form method="post" onsubmit="return confirm('Instagram-Verbindung trennen?')"><?= $hidden('ig_disconnect') ?><button class="btn btn-small btn-danger">Trennen</button></form></div><?php endif; ?>
    </section>
    <section class="card">
      <h2>So bekommst du den Zugangsschlüssel</h2>
      <ol class="howto">
        <li>Instagram-Konto in der App auf <b>Professionelles Konto</b> umstellen (Creator oder Business) – falls noch nicht geschehen.</li>
        <li>Auf <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com</a> mit Facebook anmelden → <b>App erstellen</b> → Anwendungsfall „Nachrichten und Inhalte auf Instagram verwalten“.</li>
        <li>Im App-Dashboard: <b>Instagram → API-Einrichtung mit Instagram-Login</b> öffnen.</li>
        <li>Bei „Zugangsschlüssel generieren“ auf <b>Konto hinzufügen</b> klicken und mit <b>@<?= e($S['instagram']) ?></b> anmelden.</li>
        <li>Auf <b>Token generieren</b> klicken, den langen Schlüssel kopieren und links einfügen.</li>
      </ol>
      <p class="hint">Der Schlüssel ist 60 Tage gültig und wird von der Webseite automatisch verlängert. Die App muss dafür nicht veröffentlicht werden. Bilder werden auf deinen Server kopiert – Besucher laden nichts von Instagram.</p>
    </section>
  </div>
  <?php if (!empty($igData['posts'])): ?><section class="card"><h2>Aktuell auf der Webseite</h2><div class="topposts"><?php foreach (array_slice($igData['posts'], 0, (int)$ig['count']) as $p): ?><a href="<?= e($p['permalink']) ?>" target="_blank" rel="noopener"><img src="<?= e(rs_url($p['img'])) ?>" alt=""><span><?= $p['likes'] !== null ? '♥ ' . (int)$p['likes'] . ' · 💬 ' . (int)$p['comments'] : e(date('d.m.Y', $p['ts'])) ?></span></a><?php endforeach; ?></div></section><?php endif; ?>

<?php /* ======================= SEITEN ======================= */ elseif ($tab === 'seiten'):
  $eff = rs_effective_mode($c); $launchTs = rs_ts($c['launch_at']); $codes = array_reverse(rs_codes()); $signups = array_reverse(rs_signups());
  $A = $c['angebot']; $P = $c['prelaunch'];
  $modeNames = ['live' => 'Online', 'prelaunch' => 'Vor-Launch', 'maintenance' => 'Wartung'];
  $lpUrl = rs_abs_url('angebot/'); $home = rs_abs_url('');
  $st30 = rs_stats_summary(30); $lpVisits = (int)($st30['p']['angebot/index.html'] ?? 0);
  $lpLeads = count(array_filter($leads, fn($x) => ($x['page'] ?? '') === 'angebot' && $x['created'] > time() - 30 * 86400));
?>
  <div class="page-head"><div><h1>Seiten</h1><p class="sub">Was Besucher sehen: Status, Vor-Launch-Seite und Landingpage.</p></div></div>
  <nav class="subnav"><a href="#status">Status</a><a href="#vorlaunch">Vor-Launch</a><a href="#zugaenge">Zugänge</a><a href="#anmeldungen">Anmeldungen</a><a href="#landingpage">Landingpage</a><a href="#fitcheck">Fit-Check</a></nav>

  <section class="hero-status s-<?= e($eff) ?>" id="status">
    <div><span class="eyebrow-s">Gerade sichtbar</span><h2 class="big"><?= e($modeNames[$eff]) ?></h2>
      <p class="sub"><?php if ($eff === 'prelaunch'): ?>Countdown-Seite<?= $launchTs ? ' bis <b>' . e(rs_date_de($launchTs, true)) . '</b>' . ($c['auto_launch'] ? ' – danach geht die Seite automatisch online.' : ' – danach bleibt sie gesperrt, bis du sie freischaltest.') : ' ohne Startzeitpunkt.' ?>
      <?php elseif ($eff === 'maintenance'): ?>Besucher sehen eine Hinweisseite. Du und Personen mit Zugangslink sehen die echte Seite.
      <?php else: ?>Die Webseite ist für alle erreichbar.<?php endif; ?></p></div>
    <div class="btnrow">
      <a class="btn btn-ghost btn-small" href="<?= e(rs_url('?vorschau=launch')) ?>" target="_blank" rel="noopener">Vor-Launch-Seite ansehen ↗</a>
      <?php if ($eff !== 'live'): ?><form method="post"><?= $hidden('launch_now') ?><button class="btn btn-small" onclick="return confirm('Seite jetzt für alle freischalten?')">Jetzt veröffentlichen</button></form><?php endif; ?>
    </div>
  </section>
  <section class="card">
    <h2>Status</h2>
    <form method="post" class="form"><?= $hidden('status') ?>
      <div class="modes m3">
        <?php foreach (['live' => ['Online', 'Webseite für alle sichtbar'], 'prelaunch' => ['Vor-Launch', 'Countdown-Seite mit E-Mail-Benachrichtigung'], 'maintenance' => ['Wartung', 'Hinweisseite, z. B. während Umbauten']] as $k => [$t, $d]): ?>
        <label class="mode"><input type="radio" name="mode" value="<?= $k ?>"<?= $c['mode'] === $k ? ' checked' : '' ?>><span><b><?= $t ?></b><small><?= $d ?></small></span></label><?php endforeach; ?>
      </div>
      <div class="cols"><label>Startzeitpunkt <small>(deutsche Zeit, für den Countdown)</small><input type="datetime-local" name="launch_at" value="<?= e($c['launch_at']) ?>"></label>
        <div class="stack self-end"><label class="check"><input type="checkbox" name="auto_launch" value="1"<?= $c['auto_launch'] ? ' checked' : '' ?>> Zum Startzeitpunkt automatisch online gehen</label>
          <label class="check"><input type="checkbox" name="notify_mail" value="1"<?= !empty($P['notify_mail']) ? ' checked' : '' ?>> Beim Start alle Anmeldungen per E-Mail benachrichtigen</label></div></div>
      <label>Text der Wartungsseite<textarea name="maintenance_text" rows="2" maxlength="400"><?= e($c['maintenance_text']) ?></textarea></label>
      <button class="btn">Status speichern</button>
    </form>
  </section>

  <div class="grid g-1-1">
    <section class="card" id="vorlaunch">
      <h2>Vor-Launch-Seite</h2>
      <form method="post" class="form"><?= $hidden('prelaunch_texts') ?>
        <label>Zeile oben<input name="pl_kicker" value="<?= e($P['kicker']) ?>" maxlength="60"></label>
        <div class="cols"><label>Überschrift<input name="pl_title" value="<?= e($P['title']) ?>" maxlength="80"></label><label>Schreibschrift<input name="pl_script" value="<?= e($P['script']) ?>" maxlength="60"></label></div>
        <label>Text<textarea name="pl_text" rows="3" maxlength="500"><?= e($P['text']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="pl_signup" value="1"<?= !empty($P['signup']) ? ' checked' : '' ?>> E-Mail-Benachrichtigung anbieten</label>
        <button class="btn btn-small">Texte speichern</button>
      </form>
    </section>
    <section class="card" id="zugaenge">
      <h2>Zugänge <small class="muted-s">(sehen die echte Seite vor dem Start)</small></h2>
      <form method="post" class="row-inline"><?= $hidden('add_code') ?>
        <label class="grow">Name<input name="name" placeholder="z. B. Victoria (Handy)" maxlength="80" required></label>
        <label>Gültig bis<input type="date" name="expires" min="<?= date('Y-m-d') ?>"></label>
        <button class="btn btn-small">Anlegen</button></form>
      <?php if (!$codes): ?><p class="empty">Noch keine Zugänge.</p><?php endif; ?>
      <ul class="codes"><?php foreach ($codes as $cd): $ok = rs_code_valid($cd); $link = $home . '?zugang=' . $cd['code']; ?>
        <li class="<?= $ok ? '' : 'off' ?>"><div><b><?= e($cd['name']) ?></b> <span class="tag<?= $ok ? ' on' : '' ?>"><?= $ok ? 'aktiv' : (!empty($cd['active']) ? 'abgelaufen' : 'gesperrt') ?></span>
          <small><code><?= e($cd['code']) ?></code> · <?= !empty($cd['expires']) ? 'bis ' . e(date('d.m.Y', (int)rs_ts($cd['expires']))) : 'unbegrenzt' ?> · <?= $cd['last_used'] ? 'zuletzt ' . e(rs_ago((int)$cd['last_used'])) . ' (' . (int)$cd['uses'] . '×)' : 'noch nicht genutzt' ?></small></div>
          <div class="btnrow"><button type="button" class="btn btn-ghost btn-small" data-copy="<?= e($link) ?>">Link kopieren</button>
            <form method="post"><?= $hidden('toggle_code') ?><input type="hidden" name="id" value="<?= e($cd['id']) ?>"><button class="btn btn-ghost btn-small"><?= !empty($cd['active']) ? 'Sperren' : 'Entsperren' ?></button></form>
            <form method="post" onsubmit="return confirm('Zugang löschen?')"><?= $hidden('delete_code') ?><input type="hidden" name="id" value="<?= e($cd['id']) ?>"><button class="btn btn-small btn-danger">✕</button></form></div></li>
      <?php endforeach; ?></ul>
    </section>
  </div>

  <section class="card" id="anmeldungen">
    <div class="card-h"><h2>Anmeldungen zur Benachrichtigung <span class="count-s"><?= count($signups) ?></span></h2>
      <?php if ($signups): ?><form method="post"><?= $hidden('signup_export') ?><button class="btn btn-ghost btn-small">Als CSV laden</button></form><?php endif; ?></div>
    <p class="hint">Diese Adressen bekommen beim Start eine E-Mail (wenn oben eingeschaltet) und werden danach automatisch gelöscht.</p>
    <?php if (!$signups): ?><p class="empty">Noch niemand eingetragen.</p><?php else: ?><ul class="signups"><?php foreach ($signups as $x): ?><li><span><?= e($x['email']) ?></span><small><?= e(date('d.m.Y H:i', (int)$x['ts'])) ?></small>
      <form method="post"><?= $hidden('signup_delete') ?><input type="hidden" name="email" value="<?= e($x['email']) ?>"><button class="x" aria-label="Löschen">✕</button></form></li><?php endforeach; ?></ul><?php endif; ?>
  </section>

  <section class="card" id="landingpage">
    <div class="card-h"><h2>Landingpage /angebot</h2><a class="more" href="<?= e(rs_url('angebot/')) ?>" target="_blank" rel="noopener">Ansehen ↗</a></div>
    <div class="kpis mini3"><div class="kpi"><span>Besucher (30 Tage)</span><b><?= $n($lpVisits) ?></b></div><div class="kpi"><span>Anfragen (30 Tage)</span><b><?= $n($lpLeads) ?></b></div><div class="kpi"><span>Quote</span><b><?= $lpVisits ? $n($lpLeads / $lpVisits * 100, 1) : '0' ?> %</b></div></div>
    <form method="post" class="form"><?= $hidden('angebot') ?>
      <label class="check"><input type="checkbox" name="lp_on" value="1"<?= !empty($A['on']) ? ' checked' : '' ?>> Landingpage ist erreichbar <small>(aus = Besucher landen auf der Startseite)</small></label>
      <fieldset><legend>Oberer Bereich</legend>
        <label>Zeile oben<input name="lp_kicker" value="<?= e($A['kicker']) ?>" maxlength="90"></label>
        <div class="cols"><label>Überschrift<input name="lp_title" value="<?= e($A['title']) ?>" maxlength="120"></label><label>Schreibschrift<input name="lp_script" value="<?= e($A['script']) ?>" maxlength="60"></label></div>
        <label>Text<textarea name="lp_lead" rows="2" maxlength="400"><?= e($A['lead']) ?></textarea></label>
        <label>Button-Text<input name="lp_cta" value="<?= e($A['cta']) ?>" maxlength="50"></label>
        <div class="stack"><span class="lbl-s">Vorteile (Häkchen-Liste)</span>
          <div id="lpBullets" class="stack"><?php foreach (array_pad($A['bullets'], 4, '') as $b): ?><div class="lrow l2"><input name="lp_bullets[]" value="<?= e($b) ?>" maxlength="90"><button type="button" class="x" data-remove aria-label="Löschen">✕</button></div><?php endforeach; ?></div>
          <template id="tpl-bullet"><div class="lrow l2"><input name="lp_bullets[]" maxlength="90"><button type="button" class="x" data-remove aria-label="Löschen">✕</button></div></template>
          <button type="button" class="btn btn-ghost btn-small" data-add="tpl-bullet" data-to="lpBullets">+ Vorteil</button></div>
      </fieldset>
      <fieldset><legend>Aktion / Angebot <?= !empty($A['offer_on']) && $A['offer_title'] ? '<span class="tag on">sichtbar</span>' : '<span class="tag">aus</span>' ?></legend>
        <label class="check"><input type="checkbox" name="lp_offer_on" value="1"<?= !empty($A['offer_on']) ? ' checked' : '' ?>> Aktions-Box anzeigen</label>
        <div class="cols"><label>Etikett<input name="lp_offer_badge" value="<?= e($A['offer_badge']) ?>" maxlength="24" placeholder="z. B. Herbst-Aktion"></label><label>Titel<input name="lp_offer_title" value="<?= e($A['offer_title']) ?>" maxlength="90" placeholder="z. B. Erstes Video zum Kennenlernpreis"></label></div>
        <label>Text<textarea name="lp_offer_text" rows="2" maxlength="300"><?= e($A['offer_text']) ?></textarea></label>
        <label>Gültig bis <small>(optional – zeigt einen Countdown, danach verschwindet er)</small><input type="datetime-local" name="lp_offer_until" value="<?= e($A['offer_until']) ?>"></label>
      </fieldset>
      <fieldset><legend>Abschnitte</legend>
        <div class="checks"><?php foreach (['results' => 'Ergebnisse', 'steps' => 'So läuft\'s', 'ig' => 'Instagram', 'plans' => 'Pakete', 'quotes' => 'Kundenstimmen', 'faq' => 'Fragen (FAQ)'] as $k => $t): ?><label class="check"><input type="checkbox" name="lp_show[<?= $k ?>]" value="1"<?= !empty($A['show'][$k]) ? ' checked' : '' ?>> <?= $t ?></label><?php endforeach; ?></div>
        <label class="inline">Anzahl Kundenstimmen <input type="number" name="lp_quotes_n" min="1" max="9" value="<?= (int)$A['quotes_n'] ?>"></label>
        <p class="hint">Pakete, Ergebnisse und Stimmen kommen aus „Inhalte“ – einmal pflegen, überall aktuell.</p>
      </fieldset>
      <fieldset><legend>Fragen (FAQ)</legend>
        <div id="lpFaq" class="qlist"><?php foreach ($A['faq'] as $i => $f): ?><div class="qed"><input name="faq[<?= $i ?>][q]" value="<?= e($f['q']) ?>" placeholder="Frage" maxlength="160"><textarea name="faq[<?= $i ?>][a]" rows="2" maxlength="1200" placeholder="Antwort"><?= e($f['a']) ?></textarea><div class="qed-row"><button type="button" class="x" data-remove>Löschen</button></div></div><?php endforeach; ?></div>
        <template id="tpl-faq"><div class="qed"><input name="faq[__][q]" placeholder="Frage" maxlength="160"><textarea name="faq[__][a]" rows="2" maxlength="1200" placeholder="Antwort"></textarea><div class="qed-row"><button type="button" class="x" data-remove>Löschen</button></div></div></template>
        <button type="button" class="btn btn-ghost btn-small" data-add="tpl-faq" data-to="lpFaq">+ Frage</button>
      </fieldset>
      <button class="btn">Landingpage speichern</button>
    </form>
    <h3 class="mt">Links mit Herkunft</h3>
    <p class="hint">Je nach Kanal einen eigenen Link verwenden – in der Statistik siehst du dann genau, woher Besucher und Anfragen kommen.</p>
    <div class="utm" data-base="<?= e($lpUrl) ?>">
      <?php foreach (['instagram-bio' => 'Instagram-Bio', 'instagram-story' => 'Instagram-Story', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'flyer' => 'Flyer / QR-Code', 'google' => 'Google-Profil'] as $k => $t): $u = $lpUrl . '?utm_source=' . $k; ?>
      <div class="utm-row"><span><?= e($t) ?></span><code><?= e($u) ?></code><button type="button" class="btn btn-ghost btn-small" data-copy="<?= e($u) ?>">Kopieren</button></div><?php endforeach; ?>
      <div class="utm-row"><span>Eigene Kampagne</span><input id="utmCamp" placeholder="z. B. herbst-aktion" maxlength="40"><button type="button" class="btn btn-ghost btn-small" id="utmCopy">Kopieren</button></div>
    </div>
  </section>


  <section class="card" id="fitcheck">
    <?php $K = $c['check']; $fcUrl = rs_abs_url('start/'); $fcSteps = rs_check_steps(); $fcLeads = count(array_filter($leads, fn($x) => ($x['page'] ?? '') === 'check' && $x['created'] > time() - 30 * 86400)); ?>
    <div class="card-h"><h2>Fit-Check „Zusammenarbeit starten“ <small class="muted-s">/start</small></h2><a class="more" href="<?= e(rs_url('start/')) ?>" target="_blank" rel="noopener">Ansehen ↗</a></div>
    <p class="hint">Eine Frage pro Bildschirm – ideal als Link in der Instagram-Bio. Die Antworten landen als Anfrage (markiert mit „FC“) inklusive Paket-Empfehlung.</p>
    <div class="grid g-1-1 inner">
      <div>
        <h3>Wo springen Leute ab? <small>(letzte 30 Tage)</small></h3>
        <div class="funnel"><?php $f0 = max(1, (int)($st30['c']['check-1'] ?? 0)); foreach ($fcSteps as $i => $t): $v = (int)($st30['c']['check-' . ($i + 1)] ?? 0); if ($i === count($fcSteps) - 1) $v = max($v, $fcLeads); ?>
          <div class="frow"><span><?= ($i + 1) . '. ' . e($t) ?></span><i style="--w:<?= min(100, round($v / $f0 * 100)) ?>%"></i><b><?= $n($v) ?></b></div><?php endforeach; ?></div>
      </div>
      <div>
        <h3>Profilfoto im Gruß</h3>
        <div class="img-slot"><?php if ($S['img_avatar'] ?? ''): ?><img src="<?= e(rs_url($S['img_avatar'])) ?>" alt="" style="width:84px;aspect-ratio:1;border-radius:50%"><?php else: ?><span class="avatar-ph">R.</span><?php endif; ?>
          <div><p class="hint">Quadratisches Foto von dir (Gesicht gut sichtbar). Ohne Foto erscheint das „R.“.</p>
            <form method="post" enctype="multipart/form-data"><?= $hidden('image') ?><input type="hidden" name="slot" value="avatar"><label class="btn btn-ghost btn-small file-btn">Foto wählen<input type="file" name="file" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"></label></form>
            <?php if ($S['img_avatar'] ?? ''): ?><form method="post"><?= $hidden('image_delete') ?><input type="hidden" name="slot" value="avatar"><button class="btn btn-small btn-danger">Entfernen</button></form><?php endif; ?></div></div>
        <h3 class="mt">Links</h3>
        <div class="utm"><?php foreach (['' => 'Direkt', 'instagram-bio' => 'Instagram-Bio', 'instagram-story' => 'Story / DM', 'tiktok' => 'TikTok-Bio'] as $k => $t): $u = $fcUrl . ($k ? '?utm_source=' . $k : ''); ?>
          <div class="utm-row"><span><?= e($t) ?></span><code><?= e($u) ?></code><button type="button" class="btn btn-ghost btn-small" data-copy="<?= e($u) ?>">Kopieren</button></div><?php endforeach; ?></div>
      </div>
    </div>
    <form method="post" class="form mt"><?= $hidden('check') ?>
      <label class="check"><input type="checkbox" name="fc_on" value="1"<?= !empty($K['on']) ? ' checked' : '' ?>> Fit-Check ist erreichbar <small>(aus = Hinweise auf der Webseite verschwinden)</small></label>
      <div class="cols"><label>Begrüßung<input name="fc_title" value="<?= e($K['title']) ?>" maxlength="90"></label><label>Button<input name="fc_cta" value="<?= e($K['cta']) ?>" maxlength="40"></label></div>
      <label>Text unter der Begrüßung<textarea name="fc_text" rows="2" maxlength="300"><?= e($K['text']) ?></textarea></label>
      <div class="cols"><label>Danke-Überschrift<input name="fc_done_title" value="<?= e($K['done_title']) ?>" maxlength="60"></label><label>Danke-Text<input name="fc_done_text" value="<?= e($K['done_text']) ?>" maxlength="300"></label></div>
      <fieldset><legend>Budget-Frage <?= !empty($K['budget_on']) ? '<span class="tag on">an</span>' : '<span class="tag">aus</span>' ?></legend>
        <p class="hint">Statt Preise vorzugeben, fragt der Fit-Check nach einer Spanne. Die Antwort steht in der Anfrage und fließt in die Priorität ein.</p>
        <label class="check"><input type="checkbox" name="fc_budget_on" value="1"<?= !empty($K['budget_on']) ? ' checked' : '' ?>> Budget-Frage anzeigen</label>
        <div class="cols"><label>Frage<input name="fc_budget_q" value="<?= e($K['budget_q']) ?>" maxlength="120"></label><label>Text darunter<input name="fc_budget_sub" value="<?= e($K['budget_sub']) ?>" maxlength="200"></label></div>
        <label>Antworten <small>(eine pro Zeile, max. 8 – z. B. „Unter 500 € im Monat“)</small><textarea name="fc_budget" rows="5"><?= e(implode("\n", (array)$K['budget'])) ?></textarea></label>
      </fieldset>
      <p class="hint">Die Fragen (Unternehmen, Instagram, Branche, Ziele, Follower, Posten, Umfang, Start, Budget, Nachricht, Kontakt) sind fest, damit Empfehlung, Priorität und Statistik zuverlässig funktionieren.</p>
      <button class="btn">Fit-Check speichern</button>
    </form>
  </section>
<?php /* ======================= EINSTELLUNGEN ======================= */ else: ?>
  <div class="page-head"><div><h1>Einstellungen</h1></div></div>
  <section class="card">
    <form method="post" class="form"><?= $hidden('settings') ?>
      <fieldset><legend>Kontakt</legend>
        <div class="cols">
          <label>E-Mail <small>(Empfänger der Anfragen)</small><input type="email" name="email" value="<?= e($S['email']) ?>" required></label>
          <label>Instagram-Name<input name="instagram" value="<?= e($S['instagram']) ?>" maxlength="60"></label>
          <label class="span2">Absender-Adresse für E-Mails <small>(eine Adresse deiner Domain, z. B. noreply@deine-domain.de – sonst landen Mails eher im Spam)</small><input type="email" name="mail_from" value="<?= e($S['mail_from']) ?>"></label>
        </div>
      </fieldset>
      <fieldset><legend>Impressum</legend>
        <div class="cols"><label>Name<input name="imp_name" value="<?= e($S['imp_name']) ?>" maxlength="80"></label><label>Zusatz<input name="imp_business" value="<?= e($S['imp_business']) ?>" maxlength="120"></label>
          <label>Anschrift<textarea name="imp_address" rows="3" maxlength="300"><?= e($S['imp_address']) ?></textarea></label><label>USt-IdNr. <small>(leer = ausblenden)</small><input name="imp_ustid" value="<?= e($S['imp_ustid']) ?>" maxlength="30"></label></div>
      </fieldset>
      <fieldset><legend>Datenschutzerklärung</legend>
        <p class="hint">Leer lassen = die mitgelieferte Vorlage wird angezeigt (passt zu dieser Webseite, ersetzt aber keine Rechtsberatung). Eigener Text: Absätze durch Leerzeile trennen, Überschriften mit „## “ beginnen.</p>
        <textarea name="datenschutz" rows="8" maxlength="20000" placeholder="## 1. Verantwortliche&#10;…"><?= e($S['datenschutz']) ?></textarea>
      </fieldset>
      <button class="btn">Einstellungen speichern</button>
    </form>
    <form method="post" class="mt"><?= $hidden('test_mail') ?><button class="btn btn-ghost btn-small">Test-E-Mail an <?= e($S['email']) ?> senden</button></form>
  </section>
  <div class="grid g-1-1">
    <section class="card"><h2>Statistik</h2>
      <form method="post"><?= $hidden('stats') ?><label class="check"><input type="checkbox" name="stats_on" value="1"<?= !empty($c['stats_on']) ? ' checked' : '' ?> onchange="this.form.submit()"> Besucherstatistik eingeschaltet (ohne Cookies)</label></form>
      <form method="post" class="mt" onsubmit="return confirm('Alle Statistikdaten löschen?')"><?= $hidden('stats_reset') ?><button class="btn btn-small btn-danger">Statistik zurücksetzen</button></form>
    </section>
    <section class="card"><h2>Passwort ändern</h2>
      <form method="post" class="form"><?= $hidden('admin_pw') ?>
        <label>Aktuelles Passwort<input type="password" name="old" required autocomplete="current-password"></label>
        <label>Neues Passwort <small>(mind. 10 Zeichen)</small><input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
        <button class="btn btn-small">Ändern</button></form>
    </section>
  </div>
<?php endif; ?>
</main>
<script src="admin.js" defer></script>
<?php foot();

/* ==========================================================================
   Bausteine
   ========================================================================== */
function lead_row(array $x): string {
    $o = rs_lead_options(); $d = $x['data']; [$tk, $tl] = rs_lead_temp((int)$x['score']);
    $br = $d['branche'] === 'andere' && $d['branche_text'] ? $d['branche_text'] : ($o['branche'][$d['branche']] ?? '');
    return '<li class="lead' . (empty($x['seen']) && $x['status'] === 'neu' ? ' unread' : '') . '"><a href="?tab=anfragen&id=' . e($x['id']) . '">'
        . '<span class="temp t-' . $tk . '" title="Priorität ' . (int)$x['score'] . '/100">' . $tl . '</span>'
        . '<span class="lm"><b>' . e($d['firma'] ?: $d['name']) . (['angebot' => ' <i class="lp-tag">LP</i>', 'check' => ' <i class="lp-tag fc">FC</i>'][$x['page'] ?? ''] ?? '') . '</b><small>' . e(trim(($d['firma'] ? $d['name'] . ' · ' : '') . $br . ($d['ort'] ? ' · ' . $d['ort'] : ''), ' ·')) . '</small></span>'
        . '<span class="lr"><span class="pill-s st-' . e($x['status']) . '">' . e(rs_lead_status()[$x['status']] ?? '') . '</span><span class="rec-s">' . e($x['rec']) . '</span><small>' . e(rs_ago((int)$x['created'])) . '</small></span></a></li>';
}
function quote_editor(string $i, array $q): string {
    $n = "quotes[$i]";
    return '<div class="qed"><textarea name="' . $n . '[t]" rows="2" maxlength="600" placeholder="Zitat">' . e($q['t']) . '</textarea>'
        . '<div class="qed-row"><input name="' . $n . '[n]" value="' . e($q['n']) . '" placeholder="Name" maxlength="60"><input name="' . $n . '[c]" value="' . e($q['c']) . '" placeholder="Unternehmen" maxlength="60">'
        . '<input name="' . $n . '[note]" value="' . e($q['note']) . '" placeholder="Zusatz (optional)" maxlength="200" class="grow"></div>'
        . '<div class="qed-row"><label class="check"><input type="checkbox" name="' . $n . '[on]" value="1"' . (!empty($q['on']) ? ' checked' : '') . '> sichtbar</label>'
        . '<label class="check"><input type="checkbox" name="' . $n . '[blue]" value="1"' . (!empty($q['blue']) ? ' checked' : '') . '> blaue Akzentfarbe</label>'
        . '<button type="button" class="x" data-remove aria-label="Stimme löschen">Löschen</button></div></div>';
}
function range_nav(int $range, string $tab): string {
    $o = '<nav class="range" aria-label="Zeitraum">';
    foreach ([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr'] as $k => $t) $o .= '<a href="?tab=' . $tab . '&tage=' . $k . '"' . ($range === $k ? ' aria-current="true"' : '') . '>' . $t . '</a>';
    return $o . '</nav>';
}
function spark(array $vals): string {
    $vals = array_values($vals);
    if (count($vals) < 2) return '<p class="empty">Der Verlauf erscheint ab dem zweiten Tag.</p>';
    $min = min($vals); $max = max($vals); $span = max(1, $max - $min); $w = 600; $h = 120; $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i / (count($vals) - 1) * $w, 1) . ',' . round($h - 8 - ($v - $min) / $span * ($h - 16), 1);
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none"><polyline points="0,' . $h . ' ' . implode(' ', $pts) . ' ' . $w . ',' . $h . '" class="area"/><polyline points="' . implode(' ', $pts) . '" class="line"/></svg>'
        . '<div class="chart-axis"><span>' . number_format($min, 0, ',', '.') . '</span><span>' . number_format($max, 0, ',', '.') . '</span></div>';
}
function icon(string $k): string {
    $p = ['start' => '<path d="M4 11 12 4l8 7v8a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/>', 'anfragen' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
          'statistik' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>', 'inhalte' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
          'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/>', 'seiten' => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/><path d="m10 13 2 2 4-4"/>', 'einstellungen' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>'][$k] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
function render_auth(string $type, ?string $error): void {
    head($type === 'setup' ? 'Einrichtung – Reserved. Admin' : 'Anmelden – Reserved. Admin'); ?>
<main class="auth"><form method="post" class="card auth-card">
  <span class="brand big">Reserved<span>.</span></span>
  <?php if ($type === 'setup'): ?>
    <h1>Admin einrichten</h1>
    <p class="hint">Den Einrichtungscode findest du auf dem Webspace in <code>_reserved/data/einrichtungscode.php</code> (z. B. über den Webspace-Explorer deines Hosters oder per FTP).</p>
    <?php if (PHP_SAPI === 'cli-server'): ?><p class="flash warn">Lokaler Test – Einrichtungscode: <strong><?= e(setup_code()) ?></strong></p><?php endif; ?>
    <input type="hidden" name="action" value="setup">
    <label>Einrichtungscode<input name="setup_code" required autocomplete="off" placeholder="XXXX-XXXX-XXXX-XXXX" autocapitalize="characters"></label>
    <label>Admin-Passwort <small>(mind. 10 Zeichen)</small><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Passwort wiederholen<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
    <button class="btn">Einrichten</button>
  <?php else: ?>
    <h1>Admin-Bereich</h1>
    <input type="hidden" name="action" value="login">
    <label>Passwort<input type="password" name="password" required autofocus autocomplete="current-password"></label>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
    <button class="btn">Anmelden</button>
  <?php endif; ?>
</form></main>
<?php foot();
}
function head(string $title, bool $app = false): void { ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2B2622"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Reserved. Admin"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= e($title) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%232B2622'/%3E%3Ctext x='32' y='43' text-anchor='middle' font-family='Georgia,serif' font-size='30' fill='%23D98A73'%3ER.%3C/text%3E%3C/svg%3E">
<link rel="apple-touch-icon" href="<?= e(rs_url('assets/img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="admin.css">
</head>
<body class="<?= $app ? 'app' : 'login' ?>">
<?php }
function foot(): void { echo "</body>\n</html>\n"; }
