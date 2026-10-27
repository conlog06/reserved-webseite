<?php
/* ==========================================================================
   reserved.social – Anfragen
   Mehrstufiges Formular → Paket-Empfehlung, Priorität (heiß/warm/kalt),
   E-Mail an Victoria, automatische Bestätigung an die Kund:in,
   Postfach mit Status im Admin-Bereich.
   ========================================================================== */

declare(strict_types=1);

function rs_lead_options(): array {
    return [
        'branche' => ['gastro' => 'Restaurant & Bar', 'cafe' => 'Café & Bäckerei', 'beauty' => 'Beauty & Friseur', 'praxis' => 'Praxis & Gesundheit',
                      'handwerk' => 'Handwerk', 'handel' => 'Einzelhandel', 'hotel' => 'Hotel & Ferienwohnung', 'andere' => 'Etwas anderes'],
        'ziele' => ['kunden' => 'Mehr Kund:innen vor Ort', 'anfragen' => 'Mehr Anfragen & Buchungen', 'bekannt' => 'In der Region bekannter werden',
                    'events' => 'Events & Aktionen füllen', 'personal' => 'Personal finden', 'image' => 'Professioneller Auftritt'],
        'stand' => ['none' => 'Gibt es noch nicht', 'rare' => 'Unregelmäßig', 'regular' => 'Regelmäßig – soll besser werden'],
        'umfang' => ['test' => 'Erst mal reinschnuppern', 'content' => 'Content produzieren – posten tun wir selbst', 'all' => 'Alles – inkl. Posten & Community', 'coaching' => 'Selbst lernen – Strategie & 1:1 Coaching'],
        'start' => ['asap' => 'So schnell wie möglich', 'month' => 'In den nächsten 4 Wochen', 'later' => 'Später / erst mal informieren'],
        'kontakt' => ['mail' => 'E-Mail', 'phone' => 'Telefon', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram-DM'],
        'follower' => ['none' => 'Noch kein Profil', 'u500' => 'Unter 500', 'u2k' => '500 – 2.000', 'u10k' => '2.000 – 10.000', 'o10k' => 'Über 10.000'],
        'posting' => ['never' => 'Gar nicht', 'rare' => 'Selten', 'weekly' => '1–3× pro Woche', 'daily' => 'Fast täglich'],
    ];
}
function rs_lead_status(): array {
    return ['neu' => 'Neu', 'kontakt' => 'Kontaktiert', 'angebot' => 'Angebot gesendet', 'gewonnen' => 'Kunde geworden', 'verloren' => 'Kein Auftrag'];
}

/** Erste Einschätzung – dieselbe Logik läuft im Formular (main.js) live mit */
function rs_lead_recommend(array $d): array {
    return match ($d['umfang'] ?? '') {
        'test' => ['Einzelnes Video', 'Zum Reinschnuppern ohne laufende Betreuung – du siehst, wie reserved.social arbeitet.'],
        'coaching' => ['Strategie & 1:1 Coaching', 'Du lernst, selbst Content zu erstellen und mit CapCut zu schneiden – mit Strategie und Hilfe beim Dreh.'],
        'content' => ['Content-Erstellung', 'Wir planen und drehen 9:16 Content bei dir vor Ort – posten kannst du selbst.'],
        'all' => ['Social Media Management', 'Wir übernehmen alles: Strategie, Dreh, Posten und Community.'],
        default => ['Individuell', 'Im kostenlosen Erstgespräch stellen wir zusammen, was zu dir passt.'],
    };
}

/** Priorität 0–100 (wie dringend und wie konkret) */
function rs_lead_score(array $d): int {
    $s = ['asap' => 30, 'month' => 20, 'later' => 5][$d['start'] ?? ''] ?? 10;
    $s += ['all' => 25, 'content' => 18, 'coaching' => 14, 'test' => 8][$d['umfang'] ?? ''] ?? 10;
    $s += min(16, count($d['ziele'] ?? []) * 4);
    if (($d['telefon'] ?? '') !== '') $s += 10;
    if (($d['firma'] ?? '') !== '') $s += 6;
    if (($d['insta'] ?? '') !== '') $s += 5;
    if (mb_strlen($d['nachricht'] ?? '') > 40) $s += 8;
    if (($d['budget'] ?? '') !== '' && stripos($d['budget'], 'weiß') === false) $s += 8;
    return min(100, $s);
}
function rs_lead_temp(int $score): array {
    if ($score >= 65) return ['hot', 'Heiß'];
    if ($score >= 40) return ['warm', 'Warm'];
    return ['cold', 'Kalt'];
}

function rs_leads(): array { return rs_read('leads', []); }
function rs_save_leads(array $l): void { rs_write('leads', array_values($l)); }

/** Eingaben prüfen und Anfrage speichern. Gibt [ok, fehler|lead] zurück. */
function rs_lead_create(array $in): array {
    $o = rs_lead_options();
    $str = fn(string $k, int $max) => rs_cut(str_replace(["\r", "\0"], '', strip_tags((string)($in[$k] ?? ''))), $max);
    $pick = fn(string $k) => isset($o[$k][(string)($in[$k] ?? '')]) ? (string)$in[$k] : '';
    $d = [
        'name' => $str('name', 120), 'email' => $str('email', 200), 'telefon' => $str('telefon', 40), 'firma' => $str('firma', 160),
        'ort' => $str('ort', 80), 'insta' => ltrim($str('insta', 60), '@'), 'nachricht' => $str('nachricht', 4000),
        'branche' => $pick('branche'), 'branche_text' => $str('branche_text', 80), 'stand' => $pick('stand'), 'umfang' => $pick('umfang'),
        'start' => $pick('start'), 'kontakt' => $pick('kontakt') ?: 'mail', 'paket' => $str('paket', 40),
        'follower' => $pick('follower'), 'posting' => $pick('posting'),
        'budget' => in_array((string)($in['budget'] ?? ''), (array)(rs_config()['check']['budget'] ?? []), true) ? (string)$in['budget'] : '',
        'ziele' => array_values(array_intersect(array_map('strval', (array)($in['ziele'] ?? [])), array_keys($o['ziele']))),
    ];
    $d['name'] = str_replace("\n", ' ', $d['name']);
    if ($d['name'] === '') return [false, 'Bitte gib deinen Namen an.'];
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return [false, 'Bitte gib eine gültige E-Mail-Adresse an.'];
    if (empty($in['consent'])) return [false, 'Bitte bestätige den Hinweis zum Datenschutz.'];

    [$rec, $why] = rs_lead_recommend($d);
    $score = rs_lead_score($d);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $lead = [
        'id' => bin2hex(random_bytes(6)), 'created' => time(), 'status' => 'neu', 'notes' => [], 'data' => $d,
        'rec' => $rec, 'why' => $why, 'score' => $score,
        'src' => rs_cut((string)preg_replace('/[^\w.\-: \/]/u', '', (string)($in['src'] ?? '')), 60) ?: 'direkt',
        'device' => ['m' => 'Handy', 't' => 'Tablet', 'd' => 'Computer'][rs_stats_device($ua)] . ' · ' . rs_stats_os($ua),
        'page' => in_array($in['page'] ?? '', ['angebot', 'check'], true) ? $in['page'] : 'start',
    ];
    rs_locked('leads', function () use ($lead) { $l = rs_leads(); $l[] = $lead; rs_save_leads($l); });
    rs_stats_lead();
    rs_lead_mails($lead);
    if (random_int(1, 20) === 1) rs_leads_cleanup();
    return [true, $lead];
}

function rs_lead_summary(array $lead): string {
    $o = rs_lead_options(); $d = $lead['data'];
    $lbl = fn($k) => $d[$k] !== '' ? ($o[$k][$d[$k]] ?? $d[$k]) : '–';
    $branche = $d['branche'] === 'andere' && $d['branche_text'] !== '' ? $d['branche_text'] : $lbl('branche');
    $t  = "Name:            {$d['name']}\n";
    $t .= 'Unternehmen:     ' . ($d['firma'] ?: '–') . "\n";
    $t .= "Branche:         $branche\n";
    $t .= 'Ort:             ' . ($d['ort'] ?: '–') . "\n";
    $t .= "E-Mail:          {$d['email']}\n";
    $t .= 'Telefon:         ' . ($d['telefon'] ?: '–') . "\n";
    $t .= 'Instagram:       ' . ($d['insta'] ? '@' . $d['insta'] : '–') . "\n";
    $t .= 'Lieber per:      ' . $lbl('kontakt') . "\n\n";
    $t .= 'Ziele:           ' . ($d['ziele'] ? implode(', ', array_map(fn($z) => $o['ziele'][$z], $d['ziele'])) : '–') . "\n";
    $t .= 'Social Media:    ' . $lbl('stand') . "\n";
    if (($d['follower'] ?? '') !== '') $t .= 'Follower:        ' . $o['follower'][$d['follower']] . "\n";
    if (($d['posting'] ?? '') !== '') $t .= 'Postet aktuell:  ' . $o['posting'][$d['posting']] . "\n";
    if (($d['budget'] ?? '') !== '') $t .= 'Budget:          ' . $d['budget'] . "\n";
    $t .= 'Umfang:          ' . $lbl('umfang') . "\n";
    $t .= 'Start:           ' . $lbl('start') . "\n";
    if ($d['paket'] !== '') $t .= "Paket gewählt:   {$d['paket']}\n";
    $t .= "\nNachricht:\n" . ($d['nachricht'] !== '' ? $d['nachricht'] : '–') . "\n";
    return $t;
}

function rs_lead_mails(array $lead): void {
    $c = rs_config(); $d = $lead['data'];
    [, $temp] = rs_lead_temp($lead['score']);
    $who = $d['firma'] !== '' ? $d['firma'] : $d['name'];
    $text  = "Neue Anfrage über die Webseite\n==============================\n\n";
    $text .= "Priorität:       $temp ({$lead['score']}/100)\n";
    $text .= "Empfehlung:      {$lead['rec']}\n";
    $text .= "Gekommen über:   {$lead['src']} ({$lead['device']})" . (['angebot' => ' – über die Landingpage /angebot', 'check' => ' – über den Fit-Check /start'][$lead['page'] ?? ''] ?? '') . "\n\n";
    $text .= rs_lead_summary($lead);
    $text .= "\nIm Admin-Bereich ansehen: " . rs_abs_url('admin/?tab=anfragen&id=' . $lead['id']) . "\n";
    rs_mail($c['site']['email'], "Neue Anfrage" . (['angebot' => ' (Landingpage)', 'check' => ' (Fit-Check)'][$lead['page'] ?? ''] ?? '') . ": $who – $temp, Empfehlung {$lead['rec']}", $text, $d['email'], $d['name']);

    if (!empty($c['leads']['autoreply'])) {
        $first = explode(' ', $d['name'])[0];
        $r  = "Hallo $first,\n\n";
        $r .= "danke für deine Anfrage bei reserved.social! Sie ist gut bei uns angekommen. " . $c['site']['response_text'] . "\n\n";
        $r .= "Unsere erste Einschätzung: {$lead['rec']}.\n{$lead['why']}\nDie Details stellen wir im Erstgespräch gemeinsam zusammen.\n";
        $r .= "Im Gespräch schauen wir gemeinsam, was wirklich zu dir passt.\n\n";
        $r .= "Deine Angaben im Überblick:\n---------------------------\n" . rs_lead_summary($lead);
        $r .= "\nBis bald\nVictoria\nreserved.social – Social Media für lokale Unternehmen\nInstagram: @{$c['site']['instagram']}\n";
        rs_mail($d['email'], 'Danke für deine Anfrage – reserved.social', $r, $c['site']['email'], 'reserved.social');
    }
}

/** Anfragen nach eingestellter Frist löschen (Datenschutz) */
function rs_leads_cleanup(): void {
    $months = (int)(rs_config()['leads']['keep_months'] ?? 12);
    if ($months <= 0) return;
    $limit = strtotime("-$months months");
    rs_locked('leads', function () use ($limit) {
        $l = rs_leads();
        $n = array_filter($l, fn($x) => $x['created'] >= $limit || in_array($x['status'], ['gewonnen'], true));
        if (count($n) !== count($l)) rs_save_leads($n);
    });
}
