<?php
/* ==========================================================================
   reserved.social – Funnel „Content-Plan Starter“
   1. /plan/          Name + E-Mail eintragen (Einwilligung)
   2. Bestätigungsmail (Double-Opt-in, Pflicht in Deutschland)
   3. /plan/danke/    Download + Angebot „Strategie jeden Monat mit Victoria“
                      + Termin buchen / kostenloses Erstgespräch
   4. Adressen im Admin (Liste + CSV-Export)
   ========================================================================== */
declare(strict_types=1);

function rs_funnel_subs(): array { return rs_read('funnel', []); }
/** Zustellung des PDFs: mail | page | both */
function rs_funnel_mode(?array $F = null): string { $m = (string)(($F ?? rs_config()['funnel'])['delivery'] ?? 'mail'); return in_array($m, ['mail', 'page', 'both'], true) ? $m : 'mail'; }
/** Eintrag über den Download-Schlüssel (nur für sofortigen Download, kein Abmelden) */
function rs_funnel_find_dl(string $dl): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $dl)) return null;
    foreach (rs_funnel_subs() as $s) if (!empty($s['dl']) && hash_equals($s['dl'], $dl)) return $s;
    return null;
}
function rs_funnel_save(array $subs): void { rs_write('funnel', array_values($subs)); }

/** Wo liegt das PDF? Eigenes aus dem Admin, sonst der Platzhalter. */
function rs_funnel_pdf(): string {
    $own = RS_DATA . '/plan.pdf';
    return is_file($own) ? $own : rs_root() . '/_reserved/plan-platzhalter.pdf';
}
function rs_funnel_find(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    foreach (rs_funnel_subs() as $s) if (hash_equals($s['token'], $token)) return $s;
    return null;
}
function rs_funnel_update(string $token, callable $fn): ?array {
    return rs_locked('funnel', function () use ($token, $fn) {
        $subs = rs_funnel_subs(); $hit = null;
        foreach ($subs as &$s) if (hash_equals($s['token'], $token)) { $fn($s); $hit = $s; }
        unset($s);
        if ($hit) rs_funnel_save($subs);
        return $hit;
    });
}
function rs_funnel_links(array $s): array {
    return [
        'confirm' => rs_abs_url('_reserved/api.php?a=bestaetigen&t=' . $s['token']),
        'danke' => rs_abs_url('plan/danke/?t=' . $s['token']),
        'pdf' => rs_abs_url('_reserved/api.php?a=plan&t=' . $s['token']),
        'unsub' => rs_abs_url('_reserved/api.php?a=abmelden&t=' . $s['token']),
    ];
}

/** Eintragen: legt einen (noch unbestätigten) Eintrag an und schickt die Bestätigungsmail. */
function rs_funnel_signup(array $in): array {
    $c = rs_config(); $F = $c['funnel'];
    if (empty($F['on'])) return [false, 'Der Download ist gerade nicht verfügbar.'];
    $name = str_replace(["\r", "\n"], ' ', rs_cut(strip_tags((string)($in['name'] ?? '')), 80));
    $email = rs_cut((string)($in['email'] ?? ''), 200);
    if ($name === '') return [false, 'Wie heißt du?'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Bitte gib eine gültige E-Mail-Adresse an.'];
    if (empty($in['consent'])) return [false, 'Bitte bestätige noch die Einwilligung.'];
    $src = rs_cut((string)preg_replace('/[^\w.\-: \/]/u', '', (string)($in['src'] ?? '')), 60) ?: 'direkt';

    $mode = rs_funnel_mode($F);
    $sub = rs_locked('funnel', function () use ($name, $email, $src) {
        $subs = rs_funnel_subs(); $dl = bin2hex(random_bytes(16));
        foreach ($subs as &$s) if (strcasecmp($s['email'], $email) === 0) {
            $s['name'] = $name; $s['asked'] = time(); $s['unsub'] = 0; $s['dl'] = $dl; $hit = $s; unset($s); rs_funnel_save($subs); return $hit;
        }
        unset($s);
        $new = ['id' => bin2hex(random_bytes(6)), 'token' => bin2hex(random_bytes(16)), 'dl' => $dl, 'name' => $name, 'email' => $email, 'src' => $src,
                'ts' => time(), 'asked' => time(), 'confirmed' => 0, 'downloads' => 0, 'unsub' => 0, 'nl' => ''];
        $subs[] = $new; rs_funnel_save($subs);
        return $new;
    });
    $L = rs_funnel_links($sub);
    $first = explode(' ', $sub['name'])[0];
    if ($mode === 'mail') {
        $text = "Hallo $first,\n\nschön, dass du dir den {$F['title']} holen möchtest!\n\n"
              . "Bitte bestätige kurz deine E-Mail-Adresse – danach kannst du den Plan sofort herunterladen:\n\n{$L['confirm']}\n\n"
              . "Du hast dich nicht eingetragen? Dann ignoriere diese Mail einfach – es passiert nichts weiter.\n\n"
              . "Liebe Grüße\nVictoria\nreserved.social – Social Media für lokale Unternehmen\n";
        rs_mail($sub['email'], 'Bitte bestätigen: dein ' . $F['title'], $text);
    } elseif ($mode === 'both' && !$sub['confirmed']) {
        $text = "Hallo $first,\n\nhier ist dein {$F['title']} – falls du ihn auf der Webseite noch nicht gespeichert hast:\n\n" . rs_abs_url('_reserved/api.php?a=plan&d=' . $sub['dl']) . "\n\n"
              . "Möchtest du ab und zu Social-Media-Tipps von mir bekommen? Dann bestätige bitte kurz hier:\n\n{$L['confirm']}\n\n"
              . "Ohne Bestätigung bekommst du keine weiteren Mails von uns.\n\nLiebe Grüße\nVictoria\nreserved.social – Social Media für lokale Unternehmen\n";
        rs_mail($sub['email'], 'Dein ' . $F['title'], $text);
    } elseif ($mode === 'both') {
        rs_mail($sub['email'], 'Dein ' . $F['title'], "Hallo $first,\n\nhier ist noch einmal dein {$F['title']}:\n\n" . rs_abs_url('_reserved/api.php?a=plan&d=' . $sub['dl']) . "\n\nLiebe Grüße\nVictoria\nreserved.social\n\n—\nKeine Mails mehr von uns? Hier abmelden: {$L['unsub']}\n");
    }
    if ($mode !== 'mail' && !empty($F['notify'])) rs_mail(rs_config()['site']['email'], 'Neuer Download: ' . $sub['name'], "{$sub['name']} <{$sub['email']}> hat den {$F['title']} heruntergeladen (über {$sub['src']}).\n\nAlle Einträge: " . rs_abs_url('admin/?tab=funnel') . "\n");
    rs_stats_click('plan-signup');
    return [true, $sub];
}

/** Bestätigen: Eintrag freischalten und Download-Mail schicken. */
function rs_funnel_confirm(string $token): ?array {
    $first = false;
    $s = rs_funnel_update($token, function (&$s) use (&$first) { if (!$s['confirmed']) { $s['confirmed'] = time(); $first = true; } $s['unsub'] = 0; });
    if (!$s) return null;
    if ($first) {
        $c = rs_config(); $F = $c['funnel']; $L = rs_funnel_links($s);
        $fn = explode(' ', $s['name'])[0];
        $text = (rs_funnel_mode($F) === 'mail' ? "Hallo $fn,\n\nhier ist dein {$F['title']}:\n\n{$L['pdf']}\n\n" : "Hallo $fn,\n\ndanke für deine Bestätigung – ab jetzt bekommst du ab und zu Social-Media-Tipps von mir. Den {$F['title']} findest du weiterhin hier:\n\n{$L['pdf']}\n\n")
              . ($F['offer_title'] !== '' ? $F['offer_title'] . "\n" . rs_plain($F['offer_text']) . "\n\n" : '')
              . "{$F['booking_label']} oder kostenloses Erstgespräch anfragen: " . rs_abs_url('#kontakt') . "\n\n"
              . "Viel Spaß beim Umsetzen!\nVictoria\nreserved.social\n\n—\nKeine Mails mehr von uns? Hier abmelden: {$L['unsub']}\n";
        rs_mail($s['email'], rs_funnel_mode($F) === 'mail' ? 'Dein ' . $F['title'] . ' ist da' : 'Danke für deine Bestätigung', $text);
        if (!empty($F['notify']) && rs_funnel_mode($F) === 'mail') rs_mail($c['site']['email'], 'Neuer Download: ' . $s['name'], "{$s['name']} <{$s['email']}> hat den {$F['title']} heruntergeladen (über {$s['src']}).\n\nAlle Einträge: " . rs_abs_url('admin/?tab=funnel') . "\n");
    }
    return $s;
}
function rs_plain(string $s): string { return trim((string)preg_replace('/\*\*(.+?)\*\*/', '$1', $s)); }

function rs_funnel_unsub(string $token): ?array {
    $s = rs_funnel_update($token, function (&$s) { $s['unsub'] = time(); });
    return $s;
}

/** Klick zählen (für Zahlen im Admin), unabhängig vom Browser-Signal */
function rs_stats_click(string $k): void {
    if (empty(rs_config()['stats_on'])) return;
    rs_stats_event(['c' => [$k], 's' => [], 'd' => 0]);
}

/** Einfache Seite für Links aus Mails (abmelden, ungültig) */
function rs_funnel_page(string $title, string $text, string $link = '', string $linkText = ''): void {
    header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex');
    $css = rs_url('assets/css/style.css');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . ' – reserved.social</title>'
       . '<link rel="stylesheet" href="' . e($css) . '"><meta name="robots" content="noindex"></head><body class="plan-msg"><main><a class="logo" href="' . e(rs_url('')) . '">reserved<span>.social</span></a>'
       . '<h1>' . e($title) . '</h1><p class="lead">' . e($text) . '</p>' . ($link ? '<a class="btn btn-primary" href="' . e($link) . '">' . e($linkText) . '</a>' : '') . '</main></body></html>';
    exit;
}
