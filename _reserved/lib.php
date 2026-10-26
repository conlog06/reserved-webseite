<?php
/* ==========================================================================
   Reserved. – gemeinsame Funktionen (PHP 8.1+, ohne Datenbank)
   Daten liegen in _reserved/data/*.php und beginnen mit "<?php exit; ?>",
   sind also selbst dann nicht lesbar, wenn .htaccess ignoriert würde.
   ========================================================================== */

declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
// Fehler nie auf der Seite anzeigen, sondern in _reserved/data/php-fehler.log schreiben
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(__DIR__ . '/data')) ini_set('error_log', __DIR__ . '/data/php-fehler.log');

/* Ersatz, falls die PHP-Erweiterung "mbstring" fehlt (z. B. frisch installiertes PHP unter Windows) */
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string {
        $a = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($a, $start, $length));
    }
    function mb_strlen(string $s, ?string $enc = null): int { return (int)preg_match_all('/./us', $s); }
    function mb_strtolower(string $s, ?string $enc = null): string { return strtr(strtolower($s), ['Ä' => 'ä', 'Ö' => 'ö', 'Ü' => 'ü']); }
}

const RS_DATA = __DIR__ . '/data';
const RS_GUARD = "<?php exit; ?>\n";

/* ---------- Pfade / URLs ---------- */
function rs_root(): string { return dirname(__DIR__); }
function rs_base(): string {
    static $base = null;
    if ($base !== null) return $base;
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = rtrim((string)preg_replace('~/(_reserved|admin)/[^/]*$~', '', $script), '/');
    return $base;
}
function rs_url(string $path = ''): string { return rs_base() . '/' . ltrim($path, '/'); }
function rs_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}
function rs_abs_url(string $path = ''): string {
    return (rs_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rs_url($path);
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rs_cut(string $v, int $max): string { return mb_substr(trim($v), 0, $max); }
/** Mini-Formatierung für Texte aus dem Admin: **fett** und Zeilenumbrüche */
function rs_fmt(string $s): string {
    $h = e($s);
    $h = (string)preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $h);
    return nl2br($h, false);
}

/* ---------- Datenspeicher (JSON hinter PHP-Sperre) ---------- */
function rs_read(string $name, $default) {
    $file = RS_DATA . "/$name.php";
    if (!is_file($file)) return $default;
    $raw = (string)file_get_contents($file);
    if (strpos($raw, RS_GUARD) === 0) $raw = substr($raw, strlen(RS_GUARD));
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}
function rs_write(string $name, array $data): void {
    if (!is_dir(RS_DATA)) mkdir(RS_DATA, 0750, true);
    $file = RS_DATA . "/$name.php";
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, RS_GUARD . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmp, $file);
}
/** Lesen–ändern–schreiben unter Dateisperre */
function rs_locked(string $lock, callable $fn) {
    if (!is_dir(RS_DATA)) @mkdir(RS_DATA, 0750, true);
    $h = @fopen(RS_DATA . "/$lock.lock", 'c');
    if ($h) flock($h, LOCK_EX);
    try { return $fn(); }
    finally { if ($h) { flock($h, LOCK_UN); fclose($h); } }
}

/* ---------- Inhalte & Einstellungen (Standardwerte = Inhalt der index.html) ---------- */
function rs_defaults(): array {
    $Y = '✓'; $N = '—';
    return [
        'mode' => 'live',                         // live | prelaunch | maintenance
        'launch_at' => '',                        // "2026-11-15T18:00" (deutsche Zeit)
        'auto_launch' => true,                    // zum Zeitpunkt automatisch online gehen
        'launch_mailed' => false,                 // Anmeldungen wurden beim Launch benachrichtigt
        'prelaunch' => [
            'kicker' => 'Bald online',
            'title' => 'Etwas Neues',
            'script' => 'kommt bald.',
            'text' => 'Die neue Webseite von Reserved. startet in Kürze. Trag dich ein und erfahre als Erstes, wenn es losgeht.',
            'signup' => true,
            'notify_mail' => true,
        ],
        'check' => [
            'on' => true,
            'title' => 'Hey, ich bin Victoria von Reserved.',
            'text' => 'Kurzer Fit-Check – dauert etwa eine Minute. Danach weißt du, welches Paket zu dir passt. Bereit?',
            'cta' => 'Los geht\'s',
            'done_title' => 'Danke dir!',
            'done_text' => 'Ich schaue mir deine Antworten persönlich an und melde mich bei dir.',
            'budget_on' => true,
            'budget_q' => 'Welches Budget hast du ungefähr eingeplant?',
            'budget_sub' => 'Keine Sorge, das ist unverbindlich – so kann ich dir direkt etwas Passendes vorschlagen.',
            'budget' => ['Unter 500 € im Monat', '500 – 1.000 € im Monat', '1.000 – 2.000 € im Monat', 'Über 2.000 € im Monat', 'Weiß ich noch nicht'],
        ],
        'angebot' => [
            'on' => true,
            'kicker' => 'Social Media für lokale Unternehmen',
            'title' => 'Mehr Kund:innen über Instagram & TikTok.',
            'script' => 'Ohne Aufwand für dich.',
            'lead' => 'Wir drehen bei dir vor Ort, schneiden, planen und liefern jeden Monat Content, der nach dir aussieht – nicht nach Werbung.',
            'cta' => 'Kostenloses Erstgespräch sichern',
            'offer_on' => false,
            'offer_badge' => 'Aktion',
            'offer_title' => '',
            'offer_text' => '',
            'offer_until' => '',                  // "2026-11-30T23:59" → Countdown
            'bullets' => ['Drehs vor Ort in Osnabrück & Umgebung', 'Videos, Fotos & Stories – jeden Monat', 'Eine Korrekturschleife pro Post', 'Kostenloses Erstgespräch'],
            'show' => ['results' => true, 'steps' => true, 'ig' => true, 'plans' => true, 'quotes' => true, 'faq' => true],
            'quotes_n' => 3,
            'faq' => [
                ['q' => 'Muss ich etwas vorbereiten?', 'a' => 'Nein. Wir planen jeden Monat Formate, Ideen und aktuelle Trends und stimmen den Dreh vorher kurz mit dir ab.'],
                ['q' => 'Wie oft kommt ihr zum Drehen vorbei?', 'a' => 'Je nach Paket ein- bis zweimal im Monat – direkt bei dir vor Ort.'],
                ['q' => 'Postet ihr auch für mich?', 'a' => 'Im Premium-Paket übernehmen wir das Posten auf Instagram & TikTok und das Community Management. In Starter und Standard bekommst du den fertigen Content inklusive Captions & Hashtags.'],
                ['q' => 'Was ist, wenn mir ein Video nicht gefällt?', 'a' => 'Zu jedem Post gibt es eine Korrekturschleife.'],
                ['q' => 'Kommt ihr auch außerhalb von Osnabrück?', 'a' => 'Ja. Ab 15 km berechnen wir eine kleine Anfahrtspauschale.'],
                ['q' => 'Kann ich erst mal testen?', 'a' => 'Ja – mit einem einzelnen Video ohne laufende Betreuung.'],
            ],
        ],
        'maintenance_text' => 'Wir arbeiten gerade an der Webseite. Schreib uns in der Zwischenzeit gern per Instagram oder E-Mail.',
        'admin_hash' => '',
        'secret' => '',
        'site' => [
            'email' => 'reserved.social@web.de',
            'instagram' => 'reserved.social',
            'mail_from' => '',                    // z. B. noreply@reserved-social.de (eigene Domain, sonst Spam)
            'response_text' => 'Wir melden uns in der Regel innerhalb von 24 Stunden.',
            'hero_kicker' => 'Social-Media-Agentur für lokale Unternehmen',
            'hero_title' => 'Weder Zeit noch Personal für Social Media?',
            'hero_script' => 'Wir übernehmen.',
            'hero_lead' => 'Reserved. macht lokale Unternehmen und Selbstständige sichtbar – mit Videos, Fotos und Content, der **jeden Monat** kommt. Du kümmerst dich um dein Geschäft, wir um deinen Feed.',
            'proof' => [
                ['v' => '1×', 't' => 'Drehtermin im Monat – fest im Kalender'],
                ['v' => '4–8', 't' => 'Videos im Monat, je nach Paket'],
                ['v' => '60', 't' => 'Minuten Umkreis um Osnabrück'],
            ],
            'fw_title' => 'Gemacht für Betriebe', 'fw_script' => 'von hier.',
            'fw_sub' => 'Osnabrück und eine Stunde drumherum, der Landkreis gehört dazu. So bleiben die Wege kurz und die Drehtermine fest.',
            'fw_not' => 'Onlineshops und Firmen, die bundesweit verkaufen. Dafür gibt es Agenturen, die besser passen.',
            'rh_title' => 'Ein Kanal, der', 'rh_script' => 'nicht einschläft.',
            'rh_lead' => 'Einmal motiviert anfangen und dann wieder aufhören? Verwaiste Profile schaden dem Image mehr, als sie nutzen. Wir nehmen dir den Aufwand ab und sagen dir einen festen Rhythmus zu – ob wir ihn halten, siehst du jede Woche in deinem Feed.',
            'rh_fig' => '36 %',
            'rh_fig_text' => 'der Firmen ohne Social Media nennen den Personalaufwand als Grund – häufiger als jeden anderen. Quelle: Bitkom, 2017.',
            'sv_lead' => 'Du gibst uns Zugang zu deinen Accounts und einen Drehtermin. Um den Rest kümmern wir uns – von der ersten Idee bis zum fertigen Post.',
            'sv_note' => 'Vor der Kamera stehst im Standard du selbst. Keine Sorge: Wir führen Regie, schneiden und coachen dich beim Dreh.',
            'vg_title' => 'Wir kommen nicht aus deiner Branche.', 'vg_script' => 'Zum Glück.',
            'vg_lead' => 'Das ist Absicht. Wir fragen wie Laien und erklären dein Angebot so, dass deine Kunden es verstehen. Branchenagenturen produzieren oft für Kollegen.',
            'pk_lead' => 'Drei Pakete, feste Leistungen. Du weißt vorher, was du jeden Monat bekommst. Der Preis richtet sich nach dem Umfang – nicht nach deiner Branche.',
            'bar_on' => false, 'bar_text' => '', 'bar_link_text' => '', 'bar_link' => '#kontakt',
            'plans' => [
                ['tier' => 'Einstieg', 'name' => 'Starter', 'price' => ''],
                ['tier' => 'Meistgebucht', 'name' => 'Standard', 'price' => ''],
                ['tier' => 'Rundum-sorglos', 'name' => 'Premium', 'price' => ''],
            ],
            'plan_rows' => [
                ['l' => 'Videos pro Monat', 'v' => ['4', '8', '8']],
                ['l' => 'Feed-Fotos', 'v' => ['4', '6', '8']],
                ['l' => 'Drehtermine pro Monat', 'v' => ['1', 'bis zu 2', 'bis zu 2']],
                ['l' => 'Musik & Schnitt', 'v' => [$Y, $Y, $Y]],
                ['l' => 'Instagram & TikTok', 'v' => [$Y, $Y, $Y]],
                ['l' => 'Captions & Hashtags', 'v' => [$Y, $Y, $Y]],
                ['l' => 'Story-Content (Clips & Fotos)', 'v' => [$N, '5 pro Monat', '10 pro Monat']],
                ['l' => 'Monatliche Content-Planung', 'v' => [$Y, $Y, $Y]],
                ['l' => 'Monatliche Content-Strategie', 'v' => [$N, $N, $Y]],
                ['l' => 'Community Management', 'v' => [$N, $N, $Y]],
                ['l' => 'Monatliche Auswertung', 'v' => [$N, $N, $Y]],
                ['l' => 'Korrekturschleife', 'v' => ['1 pro Post', '1 pro Post', '1 pro Post']],
                ['l' => 'Posten auf Instagram & TikTok', 'v' => [$N, $N, $Y]],
            ],
            'plans_note' => 'Preise individuell – wir melden uns innerhalb kurzer Zeit.',
            'extras' => [
                ['t' => 'Einzelnes Video', 'd' => 'Ohne laufende Betreuung – ideal zum Reinschnuppern.', 'p' => ''],
                ['t' => 'Gründerin im Bild', 'd' => 'Video mit persönlicher Empfehlung & Voiceover.', 'p' => ''],
                ['t' => 'Content-Plan Starter', 'd' => '4-Wochen-Redaktionsplan zum Selberdrehen.', 'p' => ''],
                ['t' => 'Anfahrt', 'd' => 'Außerhalb von Osnabrück (ab 15 km).', 'p' => ''],
            ],
            'results' => [
                ['v' => '5×', 't' => 'so viele Aufrufe wie die eigenen Beiträge – mindestens.', 'l' => 'Skin Aesthetics'],
                ['v' => 'Verkäufe', 't' => 'Direkte Reaktionen und Verkäufe nach dem ersten Video. „Das beste Feedback überhaupt.“', 'l' => 'Art Cake'],
                ['v' => 'Neue Gäste', 't' => 'Gäste sind aufgrund des Videos gekommen – aus einer Zielgruppe, die sonst kaum erreichbar war.', 'l' => 'Le Feu'],
            ],
            'quotes' => rs_default_quotes(),
            'about_lead' => 'Hinter Reserved. steht Victoria. Sie plant, dreht und schneidet – und ist dabei direkt bei dir vor Ort, nicht nur am Telefon.',
            'about_text' => 'Lokale Unternehmen haben selten Zeit oder Personal für Social Media. Genau da setzen wir an: Du bekommst Content, der authentisch wirkt, deine Produkte schön in Szene setzt und Menschen erreicht, die dich bisher noch nicht kannten. Manche Kund:innen begleiten wir schon seit Jahren.',
            'img_about' => '',
            'img_avatar' => '',
            'imp_name' => 'Victoria Franz',
            'imp_business' => 'Reserved. – Social Media für lokale Unternehmen',
            'imp_address' => "c/o IP-Management #6193\nLudwig-Erhard-Straße 18\n20459 Hamburg",
            'imp_ustid' => 'DE358083332',
            'datenschutz' => '',
        ],
        'ig' => [
            'on' => true,
            'token' => '',
            'token_expires' => 0,
            'token_refreshed' => 0,
            'count' => 9,
            'last_sync' => 0,
            'last_error' => '',
        ],
        'leads' => [
            'autoreply' => true,
            'keep_months' => 12,
        ],
        'stats_on' => true,
    ];
}
function rs_default_quotes(): array {
    $q = fn($t, $n, $c, $note = '', $blue = false) => ['t' => $t, 'n' => $n, 'c' => $c, 'note' => $note, 'blue' => $blue, 'on' => true];
    return [
        $q('Mit Immobilienagenturen hatte ich schon gearbeitet, aber deren Content war mir zu immolastig. Gebucht habe ich Reserved., gerade weil sie nicht aus meiner Branche kommen: Sie produzieren für Käufer, nicht für Kollegen.', 'Frauke Imming', 'Imming Immobilien', 'Inhaberin, Osnabrück', true),
        $q('Du hast sofort verstanden, was uns wichtig ist und was wir mit dem Video transportieren möchten.', 'Florian', 'Le Feu', 'Auch vor Ort war alles sehr entspannt und professionell.'),
        $q('Die Videos erzielen mindestens fünfmal so viele Aufrufe wie unsere eigenen Beiträge.', 'Dajana', 'Skin Aesthetics', 'Das zeigt, welchen Unterschied professionell produzierter Content machen kann.', true),
        $q('Nach deiner Werbung hatten wir direkte Reaktionen und auch Verkäufe. Das beste Feedback überhaupt.', 'Aline', 'Art Cake'),
        $q('Das Video wirkt nicht wie klassische Werbung – man kauft dir wirklich ab, dass du das, was du dort zeigst, selbst cool findest.', 'Florian', 'Le Feu', 'Gäste sind aufgrund des Videos zu uns gekommen.'),
        $q('Ob Vorbereitung, Kommunikation oder die Drehs vor Ort: alles ist immer zuverlässig, pünktlich und professionell.', 'Dajana', 'Skin Aesthetics', 'Wir arbeiten schon seit mehreren Jahren mit Victoria zusammen.', true),
        $q('Deine Videos sind authentisch und setzen unsere Produkte trotzdem richtig schön in Szene.', 'Aline', 'Art Cake', 'Wir erreichen dadurch eine viel größere Zielgruppe.'),
        $q('Wir erreichen über dich auch eine andere Zielgruppe, die wir über unsere klassischen Kanäle wahrscheinlich nicht erreichen würden.', 'Florian', 'Le Feu'),
        $q('Wir sind menschlich als auch fachlich sehr zufrieden. Für uns stimmt einfach das Gesamtpaket.', 'Dajana', 'Skin Aesthetics', 'Dass wir bereits seit Jahren mit Victoria zusammenarbeiten, spricht eigentlich schon für sich.', true),
        $q('Die Zusammenarbeit mit dir ist einfach super angenehm und unkompliziert.', 'Aline', 'Art Cake', 'Die Kommunikation klappt immer schnell, vor Ort ist es total entspannt.'),
        $q('Die Inhalte wirken authentisch und gleichzeitig professionell umgesetzt.', 'Dajana', 'Skin Aesthetics', '', true),
        $q('Wir würden dich jederzeit weiterempfehlen. Die Zusammenarbeit war einfach angenehm und unkompliziert.', 'Florian', 'Le Feu'),
        $q('Man kann offen über alles sprechen, eigene Ideen einbringen und gemeinsam kreativ werden.', 'Dajana', 'Skin Aesthetics', '', true),
        $q('Ich würde jederzeit wieder mit dir zusammenarbeiten und dich definitiv weiterempfehlen.', 'Aline', 'Art Cake'),
    ];
}

function rs_config(): array {
    $c = rs_read('config', []);
    $d = rs_defaults();
    foreach ($d as $k => $v) {
        if (!array_key_exists($k, $c)) $c[$k] = $v;
        elseif (is_array($v) && is_array($c[$k]) && array_keys($v) !== range(0, count($v) - 1)) $c[$k] = array_merge($v, $c[$k]);
    }
    /* Kennzahlen im Hero: alte Standardwerte durch die neuen ersetzen (nur wenn nie geändert) */
    if (($c['site']['proof'][0]['v'] ?? '') === '5×' && ($c['site']['proof'][1]['t'] ?? '') === 'Videos pro Monat im Standard-Paket') $c['site']['proof'] = $d['site']['proof'];
    foreach ((array)($c['site']['proof'] ?? []) as $i => $p) if (($p['t'] ?? '') === ($old = ['Drehtermin im Monat, mindestens. Steht vorher fest im Kalender.', 'Videos im Monat, je nach Paket. Fotos und Storys kommen dazu.', 'Minuten Umkreis um Osnabrück. Wir kommen zu dir, nicht umgekehrt.'][$i] ?? null)) $c['site']['proof'][$i]['t'] = $d['site']['proof'][$i]['t'];
    foreach (['hero_kicker' => 'Social-Media-Agentur', 'hero_title' => 'Dein Platz im Feed ist', 'hero_script' => 'reserviert.'] as $k => $v) if (($c['site'][$k] ?? null) === $v) $c['site'][$k] = $d['site'][$k];
    if (str_starts_with((string)($c['site']['hero_lead'] ?? ''), 'Wir drehen bei dir vor Ort, schneiden, texten und posten.')) $c['site']['hero_lead'] = $d['site']['hero_lead'];
    return $c;
}
function rs_save_config(array $c): void { rs_write('config', $c); }
function rs_secret(): string {
    $c = rs_config();
    if (empty($c['secret'])) { $c['secret'] = bin2hex(random_bytes(32)); rs_save_config($c); }
    return $c['secret'];
}

/* ---------- Admin-Sitzung ---------- */
function rs_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('reserved_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => rs_base() ?: '/', 'secure' => rs_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
function rs_is_admin(): bool {
    if (empty($_COOKIE['reserved_admin'])) return false;
    rs_session();
    return !empty($_SESSION['rs_admin']) && ($_SESSION['rs_admin_until'] ?? 0) > time();
}
function rs_csrf(): string {
    rs_session();
    if (empty($_SESSION['rs_csrf'])) $_SESSION['rs_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['rs_csrf'];
}
function rs_check_csrf(): bool {
    rs_session();
    return !empty($_POST['csrf']) && !empty($_SESSION['rs_csrf']) && hash_equals($_SESSION['rs_csrf'], (string)$_POST['csrf']);
}

/* ---------- Schutz gegen Durchprobieren / Spam ---------- */
function rs_ip(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . rs_secret()); }
function rs_throttled(string $bucket, int $max = 8, int $window = 900): bool {
    $t = rs_read('throttle', []);
    $now = time();
    $hits = array_filter($t[$bucket . ':' . rs_ip()] ?? [], fn($ts) => $ts > $now - $window);
    return count($hits) >= $max;
}
function rs_hit(string $bucket, int $window = 900): void {
    rs_locked('throttle', function () use ($bucket, $window) {
        $t = rs_read('throttle', []);
        $now = time();
        foreach ($t as $k => $hits) { $t[$k] = array_values(array_filter($hits, fn($ts) => $ts > $now - max($window, 3600))); if (!$t[$k]) unset($t[$k]); }
        $t[$bucket . ':' . rs_ip()][] = $now;
        rs_write('throttle', $t);
    });
}

/* ---------- E-Mail ---------- */
function rs_mail(string $to, string $subject, string $text, string $replyTo = '', string $replyName = ''): bool {
    $c = rs_config();
    $from = trim((string)$c['site']['mail_from']);
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/^www\./', '', (string)preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $from = 'noreply@' . $host;
    }
    $clean = fn($s) => str_replace(["\r", "\n"], ' ', $s);
    $headers = 'From: =?UTF-8?B?' . base64_encode('Reserved.') . "?= <$from>\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . ($replyName !== '' ? '=?UTF-8?B?' . base64_encode($clean($replyName)) . '?= ' : '') . "<$replyTo>\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    if (PHP_SAPI === 'cli-server') { // lokaler Test: Mail in Datei statt versenden
        @file_put_contents(RS_DATA . '/mail-test.log', "TO: $to\nSUBJECT: $subject\n$headers\n$text\n\n-----\n", FILE_APPEND);
        return true;
    }
    return @mail($to, '=?UTF-8?B?' . base64_encode($clean($subject)) . '?=', $text, $headers, '-f' . $from);
}

/** "Dienstag, 4. November 2026, 18:00 Uhr" */
function rs_date_de(int $ts, bool $withDay = false, bool $withTime = true): string {
    $tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    $monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return ($withDay ? $tage[(int)date('w', $ts)] . ', ' : '') . date('j', $ts) . '. ' . $monate[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ($withTime ? ', ' . date('H:i', $ts) . ' Uhr' : '');
}
function rs_ago(int $ts): string {
    $d = time() - $ts;
    if ($d < 60) return 'gerade eben';
    if ($d < 3600) return 'vor ' . (int)floor($d / 60) . ' Min.';
    if ($d < 86400) return 'vor ' . (int)floor($d / 3600) . ' Std.';
    if ($d < 172800) return 'gestern';
    if ($d < 86400 * 30) return 'vor ' . (int)floor($d / 86400) . ' Tagen';
    return date('d.m.Y', $ts);
}

/* ---------- Vor-Launch: Zeitpunkt, Zugänge, Anmeldungen ---------- */
const RS_COOKIE = 'reserved_zugang';
function rs_ts(?string $local): ?int {
    if (!$local) return null;
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $local, new DateTimeZone('Europe/Berlin'));
    return $d ? $d->getTimestamp() : null;
}
/** Tatsächlich gültiger Modus (Vor-Launch wird zum Zeitpunkt automatisch online) */
function rs_effective_mode(array $c): string {
    if ($c['mode'] === 'prelaunch' && $c['auto_launch']) {
        $t = rs_ts($c['launch_at']);
        if ($t !== null && time() >= $t) return 'live';
    }
    return $c['mode'];
}
function rs_codes(): array { return rs_read('codes', []); }
function rs_save_codes(array $codes): void { rs_write('codes', array_values($codes)); }
function rs_new_code(): string {
    $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return substr($s, 0, 4) . '-' . substr($s, 4);
}
function rs_norm_code(string $c): string { return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $c)); }
function rs_code_valid(array $code): bool {
    if (empty($code['active'])) return false;
    if (!empty($code['expires'])) { $t = rs_ts($code['expires']); if ($t !== null && time() > $t) return false; }
    return true;
}
function rs_find_code(string $input): ?array {
    $n = rs_norm_code($input);
    if ($n === '') return null;
    foreach (rs_codes() as $code) if (hash_equals(rs_norm_code($code['code']), $n)) return $code;
    return null;
}
function rs_touch_code(string $id): void {
    rs_locked('codes', function () use ($id) {
        $codes = rs_codes();
        foreach ($codes as &$code) if ($code['id'] === $id) { $code['last_used'] = time(); $code['uses'] = ($code['uses'] ?? 0) + 1; }
        unset($code); rs_save_codes($codes);
    });
}
function rs_sign(string $v): string { return hash_hmac('sha256', $v, rs_secret()); }
function rs_grant_cookie(string $who, int $days = 30): void {
    $exp = time() + $days * 86400; $val = $who . '|' . $exp;
    setcookie(RS_COOKIE, base64_encode($val . '|' . rs_sign($val)), ['expires' => $exp, 'path' => rs_base() ?: '/', 'secure' => rs_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
function rs_clear_cookie(): void {
    setcookie(RS_COOKIE, '', ['expires' => time() - 3600, 'path' => rs_base() ?: '/', 'secure' => rs_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
/** "code:<id>" oder null */
function rs_cookie_who(): ?string {
    $raw = $_COOKIE[RS_COOKIE] ?? '';
    if ($raw === '' || ($dec = base64_decode($raw, true)) === false) return null;
    $parts = explode('|', $dec);
    if (count($parts) !== 3) return null;
    [$who, $exp, $sig] = $parts;
    if (!hash_equals(rs_sign($who . '|' . $exp), $sig) || time() > (int)$exp) return null;
    if (strpos($who, 'code:') === 0) foreach (rs_codes() as $code) if ('code:' . $code['id'] === $who && rs_code_valid($code)) return $who;
    return null;
}
function rs_signups(): array { return rs_read('signups', []); }

/** Beim (automatischen) Launch alle Anmeldungen einmalig benachrichtigen */
function rs_launch_notify(): int {
    return (int)rs_locked('launch', function () {
        $c = rs_config();
        if (!empty($c['launch_mailed']) || empty($c['prelaunch']['notify_mail'])) return 0;
        $c['launch_mailed'] = true; rs_save_config($c);
        $n = 0; $url = rs_abs_url('');
        foreach (rs_signups() as $s) {
            $text = "Hallo!\n\nDu wolltest Bescheid wissen, sobald es losgeht – jetzt ist es so weit: Die neue Webseite von Reserved. ist online.\n\n$url\n\n"
                  . "Social Media für lokale Unternehmen – wir freuen uns auf dich!\n\nVictoria\nReserved.\n\n"
                  . "(Du bekommst diese E-Mail einmalig, weil du dich auf der Vor-Launch-Seite eingetragen hast. Deine Adresse wird danach gelöscht.)";
            if (rs_mail((string)$s['email'], 'Reserved. ist online', $text, (string)$c['site']['email'], 'Reserved.')) $n++;
        }
        rs_write('signups', []); // Datensparsam: nach dem Versand löschen
        return $n;
    });
}

require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/instagram.php';
require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/render.php';
