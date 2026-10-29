<?php
/* ==========================================================================
   reserved.social – Besucherstatistik (ohne Cookies, ohne gespeicherte IP-Adressen)
   Server zählt jeden Seitenaufruf (Seite, Herkunft, Gerät, System, Browser,
   Uhrzeit). Ein kleines Signal aus dem Browser ergänzt: welche Abschnitte
   gesehen wurden, Klicks auf Buttons und die Verweildauer.
   "Besucher" werden über einen Tages-Hash (IP + Browser + Tages-Zufallswert)
   gezählt – der Zufallswert wird täglich verworfen, niemand ist wiedererkennbar.
   Admins, Bots und Browser mit "Do Not Track"/GPC werden nicht gezählt.
   ========================================================================== */

declare(strict_types=1);

const RS_STATS_KEEP_MONTHS = 14;

function rs_stats_pages(): array {
    static $ls = null;
    if ($ls === null) { $ls = []; foreach (rs_services(rs_config()) as $x) $ls['leistungen/' . $x['slug']] = 'Leistung: ' . $x['t']; }
    return ['index.html' => 'Startseite', 'angebot/index.html' => 'Landingpage /angebot', 'start/index.html' => 'Fit-Check /start', 'ueber-mich/index.html' => 'Über mich /ueber-mich', 'ugc/index.html' => 'UGC /ugc', 'termin/index.html' => 'Terminbuchung /termin', 'plan/index.html' => 'Download /plan', 'plan/danke/index.html' => 'Danke-Seite /plan/danke'] + $ls + ['(launch)' => 'Vor-Launch-Seite', '(wartung)' => 'Wartungsseite', 'andere' => 'Andere'];
}
/** Abschnitte der Seite in Reihenfolge (für den "Wie weit wird gescrollt"-Trichter) */
function rs_stats_sections(): array {
    return ['hero' => 'Start', 'warum' => 'Das Problem', 'fuer-wen' => 'Für wen', 'leistungen' => 'Leistungen', 'rhythmus' => 'Rhythmus', 'ablauf' => 'Ablauf',
            'pakete' => 'Pakete', 'feed' => 'Instagram', 'feedback' => 'Feedback', 'ueber' => 'Über mich', 'faq' => 'Häufige Fragen', 'kontakt' => 'Kontakt', 'reels' => 'Reel-Statistiken', 'reichweite' => 'UGC: Reichweite', 'zielgruppe' => 'UGC: Zielgruppe', 'videos' => 'UGC: Videos', 'marken' => 'UGC: Marken'];
}
/** Klickziele mit lesbarem Namen */
function rs_stats_clicks(): array {
    $plans = [];
    foreach (rs_config()['site']['plans'] as $i => $p) $plans['plan-' . ($i + 1)] = 'Paket „' . $p['name'] . '“ angefragt';
    $check = [];
    foreach (rs_check_steps() as $i => $t) $check['check-' . ($i + 1)] = 'Fit-Check ' . ($i + 1) . ': ' . $t;
    return $plans + $check + ['cta-hero' => 'Hero: Erstgespräch', 'cta-nav' => 'Menü: Erstgespräch', 'whatsapp' => 'WhatsApp geöffnet', 'about-page' => 'Link zu „Über mich“', 'about-hero' => 'Start: Victoria / Über mich', 'plan-signup' => 'Funnel: eingetragen', 'plan-confirm' => 'Funnel: E-Mail bestätigt', 'plan-download' => 'Funnel: PDF geladen', 'book-call' => 'Funnel: Termin buchen geklickt', 'book-free' => 'Funnel: Erstgespräch geklickt', 'plan-cta' => 'Link zum Download geklickt', 'freebie' => 'Content-Plan Starter angefragt (DM)', 'offer-dfy' => 'Angebot: Wir machen das für dich', 'offer-coaching' => 'Angebot: Coaching anfragen', 'reel-case' => 'Reel-Statistik geöffnet', 'ugc-page' => 'Link zur UGC-Seite', 'rh-chart' => 'Rhythmus-Grafik angetippt', 'termin-bestaetigt' => 'Termin bestätigt', 'termin-abgelehnt' => 'Termin passt nicht', 'termin-ics' => 'Termin: Kalendereintrag geladen', 'ugc-anfrage' => 'UGC: Anfrage gesendet', 'ugc-form' => 'UGC: zum Formular', 'ugc-mail' => 'UGC: Anfrage per Mail', 'ugc-ig' => 'UGC: Instagram', 'ugc-tt' => 'UGC: TikTok', 'ugc-portfolio' => 'UGC: Model-Portfolio', 'cta-leistung' => 'Leistungsseite: Erstgespräch', 'cta-leistung-check' => 'Leistungsseite: Fit-Check', 'cta-sticky' => 'Handy-Leiste: Anfrage', 'dm-sticky' => 'Handy-Leiste: DM',
            'mail' => 'E-Mail-Adresse geklickt', 'dm' => 'Instagram-DM geklickt', 'ig-follow' => 'Instagram: Folgen', 'ig-post' => 'Instagram-Post geöffnet',
            'ig-open' => 'Post auf Instagram geöffnet', 'check-cta' => 'Fit-Check-Link geklickt', 'form-start' => 'Anfrage begonnen', 'form-step2' => 'Anfrage: Schritt 2', 'form-step3' => 'Anfrage: Schritt 3'];
}

/** Schritte des Fit-Checks (für den Trichter im Admin) */
function rs_check_steps(): array {
    return ['Start', 'Unternehmen', 'Instagram', 'Branche', 'Ziele', 'Follower', 'Posten', 'Umfang', 'Start-Zeit', 'Budget', 'Nachricht', 'Kontakt', 'Gesendet'];
}

function rs_stats_is_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('~bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|curl|wget|python|java/|go-http|monitor|uptime|facebookexternalhit|whatsapp|telegram|discord|semrush|ahrefs|playwright~i', $ua);
}
function rs_stats_device(string $ua): string {
    if (preg_match('~iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))~i', $ua)) return 't';
    if (preg_match('~Mobi|iPhone|iPod|Android|Windows Phone~i', $ua)) return 'm';
    return 'd';
}
function rs_stats_os(string $ua): string {
    if (preg_match('~iPhone|iPad|iPod~i', $ua)) return 'iOS';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) return 'macOS';
    if (stripos($ua, 'CrOS') !== false) return 'ChromeOS';
    if (stripos($ua, 'Linux') !== false) return 'Linux';
    return 'Andere';
}
function rs_stats_browser(string $ua): string {
    if (stripos($ua, 'Instagram') !== false) return 'Instagram-App';
    if (preg_match('~FBAN|FBAV|FB_IAB~', $ua)) return 'Facebook-App';
    if (stripos($ua, 'TikTok') !== false || stripos($ua, 'musical_ly') !== false || stripos($ua, 'BytedanceWebview') !== false) return 'TikTok-App';
    if (stripos($ua, 'SamsungBrowser') !== false) return 'Samsung Internet';
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) return 'Opera';
    if (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) return 'Firefox';
    if (stripos($ua, 'CriOS') !== false || stripos($ua, 'Chrome') !== false) return 'Chrome';
    if (stripos($ua, 'Safari') !== false) return 'Safari';
    return 'Andere';
}
function rs_stats_source(string $ua): string {
    $utm = trim((string)($_GET['utm_source'] ?? ''));
    if ($utm !== '') return mb_strtolower(mb_substr((string)preg_replace('/[^\w.\- ]/u', '', $utm), 0, 40));
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = strtolower((string)parse_url($ref, PHP_URL_HOST));
    if ($host === '') {
        // In-App-Browser schicken oft keinen Referrer – trotzdem richtig zuordnen
        if (stripos($ua, 'Instagram') !== false) return 'Instagram';
        if (preg_match('~FBAN|FBAV~', $ua)) return 'Facebook';
        if (stripos($ua, 'TikTok') !== false || stripos($ua, 'musical_ly') !== false) return 'TikTok';
        return 'direkt';
    }
    $own = (string)preg_replace('/:\d+$/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === $own || ($own !== '' && str_ends_with($host, '.' . $own))) return 'intern';
    $map = ['maps.google' => 'Google Maps', 'google.' => 'Google', 'bing.' => 'Bing', 'duckduckgo.' => 'DuckDuckGo', 'ecosia.' => 'Ecosia',
            'instagram.' => 'Instagram', 'l.instagram' => 'Instagram', 'facebook.' => 'Facebook', 'fb.' => 'Facebook', 'linkedin.' => 'LinkedIn',
            'tiktok.' => 'TikTok', 'youtube.' => 'YouTube', 'pinterest.' => 'Pinterest', 'whatsapp.' => 'WhatsApp', 'linktr.ee' => 'Linktree',
            't.co' => 'X / Twitter', 'twitter.' => 'X / Twitter', 'loggx.' => 'LoggX'];
    foreach ($map as $needle => $name) if (strpos($host, $needle) !== false) return $name;
    return (string)preg_replace('/^www\./', '', $host);
}
function rs_stats_skip(string $ua): bool {
    return rs_stats_is_bot($ua)
        || ($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1'
        || strtolower((string)($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '')) === 'prefetch';
}

function rs_stats_empty_day(): array {
    return ['v' => 0, 'u' => 0, 'a' => 0, 'p' => [], 'r' => [], 'g' => ['m' => 0, 't' => 0, 'd' => 0], 'os' => [], 'br' => [],
            'h' => array_fill(0, 24, 0), 'cp' => [], 's' => [], 'c' => [], 't' => 0, 'e' => 0, 'ev' => 0];
}
function rs_stats_update(callable $fn): void {
    $day = date('Y-m-d');
    rs_locked('stats', function () use ($fn, $day) {
        $file = 'stats-' . substr($day, 0, 7);
        $m = rs_read($file, []);
        $d = array_merge(rs_stats_empty_day(), $m[$day] ?? []);
        $fn($d);
        $m[$day] = $d;
        rs_write($file, $m);
    });
}

/** Seitenaufruf zählen (aus gate.php) */
function rs_stats_track(string $page): void {
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (rs_stats_skip($ua)) return;
        $page = strtolower($page);
        if (!isset(rs_stats_pages()[$page])) $page = 'andere';
        $day = date('Y-m-d');
        $source = rs_stats_source($ua);
        $camp = mb_substr((string)preg_replace('/[^\w.\- ]/u', '', (string)($_GET['utm_campaign'] ?? '')), 0, 40);
        rs_stats_update(function (array &$d) use ($page, $day, $source, $ua, $camp) {
            $today = rs_read('stats-heute', []);
            if (($today['day'] ?? '') !== $day) $today = ['day' => $day, 'salt' => bin2hex(random_bytes(16)), 'seen' => []];
            $hash = substr(hash('sha256', $today['salt'] . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua), 0, 16);
            $isNew = !isset($today['seen'][$hash]);
            if ($isNew) { $today['seen'][$hash] = 1; rs_write('stats-heute', $today); }
            $d['v']++;
            $d['p'][$page] = ($d['p'][$page] ?? 0) + 1;
            $d['h'][(int)date('G')]++;
            if ($isNew) {
                $d['u']++;
                $dev = rs_stats_device($ua); $d['g'][$dev] = ($d['g'][$dev] ?? 0) + 1;
                $os = rs_stats_os($ua); $d['os'][$os] = ($d['os'][$os] ?? 0) + 1;
                $br = rs_stats_browser($ua); $d['br'][$br] = ($d['br'][$br] ?? 0) + 1;
                if ($source !== 'intern') $d['r'][$source] = ($d['r'][$source] ?? 0) + 1;
                if ($camp !== '') $d['cp'][$camp] = ($d['cp'][$camp] ?? 0) + 1;
                if (count($d['r']) > 60) { arsort($d['r']); $d['r'] = array_slice($d['r'], 0, 50, true); }
            }
        });
        if (random_int(1, 200) === 1) rs_stats_cleanup();
    } catch (Throwable $e) { /* Statistik darf die Seite nie stören */ }
}

/** Signal aus dem Browser: gesehene Abschnitte, Klicks, Verweildauer */
function rs_stats_event(array $in): void {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (rs_stats_skip($ua) || rs_is_admin()) return;
    $secs = array_values(array_intersect(array_map('strval', (array)($in['s'] ?? [])), array_keys(rs_stats_sections())));
    $clicks = array_values(array_intersect(array_map('strval', (array)($in['c'] ?? [])), array_keys(rs_stats_clicks())));
    $dur = max(0, min(1800, (int)($in['d'] ?? 0)));
    $engaged = !empty($in['e']);
    if (!$secs && !$clicks && !$dur && !$engaged) return;
    rs_stats_update(function (array &$d) use ($secs, $clicks, $dur, $engaged) {
        foreach (array_slice($secs, 0, 12) as $s) $d['s'][$s] = ($d['s'][$s] ?? 0) + 1;
        foreach (array_slice($clicks, 0, 20) as $c) $d['c'][$c] = ($d['c'][$c] ?? 0) + 1;
        $d['t'] += $dur;
        if ($engaged) $d['e']++;
        $d['ev']++;
    });
}

/** Anfrage zählen (aus leads.php) */
function rs_stats_lead(): void {
    try { rs_stats_update(function (array &$d) { $d['a']++; }); } catch (Throwable $e) {}
}

function rs_stats_cleanup(): void {
    $limit = date('Y-m', strtotime('-' . RS_STATS_KEEP_MONTHS . ' months'));
    foreach (glob(RS_DATA . '/stats-????-??.php') ?: [] as $f) if (substr(basename($f, '.php'), 6) < $limit) @unlink($f);
}
function rs_stats_reset(): void {
    rs_locked('stats', function () { foreach (glob(RS_DATA . '/stats-*.php') ?: [] as $f) @unlink($f); });
}

/** Auswertung für die letzten $days Tage + Vergleichszeitraum */
function rs_stats_summary(int $days): array {
    $out = rs_stats_empty_day();
    $out += ['days' => [], 'wd' => array_fill(0, 7, 0), 'prev' => ['u' => 0, 'v' => 0, 'a' => 0]];
    $cache = [];
    $get = function (string $day) use (&$cache) {
        $f = 'stats-' . substr($day, 0, 7);
        if (!isset($cache[$f])) $cache[$f] = rs_read($f, []);
        return $cache[$f][$day] ?? null;
    };
    $sum = fn(array &$into, array $from) => array_walk($from, function ($n, $k) use (&$into) { $into[$k] = ($into[$k] ?? 0) + $n; });
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $d = $get($day);
        $out['days'][] = ['day' => $day, 'u' => (int)($d['u'] ?? 0), 'v' => (int)($d['v'] ?? 0), 'a' => (int)($d['a'] ?? 0)];
        if (!$d) continue;
        foreach (['v', 'u', 'a', 't', 'e', 'ev'] as $k) $out[$k] += (int)($d[$k] ?? 0);
        foreach (['p', 'r', 'g', 'os', 'br', 'cp', 's', 'c'] as $k) $sum($out[$k], $d[$k] ?? []);
        foreach (($d['h'] ?? []) as $h => $n) $out['h'][$h] += $n;
        $out['wd'][(int)date('N', strtotime($day)) - 1] += (int)($d['v'] ?? 0);
    }
    for ($i = 2 * $days - 1; $i >= $days; $i--) {
        $d = $get(date('Y-m-d', strtotime("-$i days")));
        if ($d) foreach (['u', 'v', 'a'] as $k) $out['prev'][$k] += (int)($d[$k] ?? 0);
    }
    foreach (['p', 'r', 'os', 'br', 'cp', 'c'] as $k) arsort($out[$k]);
    return $out;
}
