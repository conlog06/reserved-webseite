<?php
/* ==========================================================================
   reserved.social – Anfragen von Marken über die UGC-Seite (/ugc/)
   Formular im E-Mail-Look → Mail an Victorias Booking-Adresse + Liste im Admin
   ========================================================================== */
declare(strict_types=1);

function rs_ugc_msgs(): array { return rs_read('ugc-anfragen', []); }

function rs_ugc_create(array $in): array {
    $c = rs_config(); $U = $c['ugc'];
    if (empty($U['on'])) return [false, 'Die Seite ist gerade nicht verfügbar.'];
    $one = fn(string $k, int $max) => rs_cut(str_replace(["\r", "\n"], ' ', trim(strip_tags((string)($in[$k] ?? '')))), $max);
    $m = ['id' => bin2hex(random_bytes(6)), 'ts' => time(), 'name' => $one('name', 80), 'email' => rs_cut(trim((string)($in['email'] ?? '')), 200),
          'firma' => $one('firma', 120), 'betreff' => $one('betreff', 140), 'text' => rs_cut(trim(strip_tags(str_replace("\r", '', (string)($in['nachricht'] ?? '')))), 4000),
          'src' => rs_cut((string)preg_replace('/[^\w.\-: \/]/u', '', (string)($in['src'] ?? '')), 60) ?: 'direkt', 'seen' => false];
    if ($m['name'] === '') return [false, 'Wie heißt du?'];
    if (!filter_var($m['email'], FILTER_VALIDATE_EMAIL)) return [false, 'Bitte gib eine gültige E-Mail-Adresse an.'];
    if (mb_strlen($m['text']) < 10) return [false, 'Schreib bitte kurz, worum es geht.'];
    if (empty($in['consent'])) return [false, 'Bitte bestätige den Datenschutz-Hinweis.'];
    rs_locked('ugc-anfragen', function () use ($m) { $l = rs_ugc_msgs(); $l[] = $m; rs_write('ugc-anfragen', array_slice($l, -500)); });
    $to = filter_var($U['email'], FILTER_VALIDATE_EMAIL) ? $U['email'] : $c['site']['email'];
    $subject = $m['betreff'] !== '' ? $m['betreff'] : 'Anfrage über die UGC-Seite';
    rs_mail($to, $subject . ' – ' . ($m['firma'] ?: $m['name']),
        "Neue Anfrage über die UGC-Seite:\n\nVon: {$m['name']} <{$m['email']}>\n" . ($m['firma'] !== '' ? "Marke: {$m['firma']}\n" : '') . "Betreff: $subject\n\n{$m['text']}\n\n—\nEinfach auf diese Mail antworten, um {$m['name']} zu schreiben.\nHerkunft: {$m['src']}\n",
        $m['email'], $m['name']);
    $first = explode(' ', $m['name'])[0];
    rs_mail($m['email'], 'Danke für deine Nachricht', "Hallo $first,\n\ndanke für deine Nachricht – sie ist angekommen! Ich melde mich in der Regel innerhalb von zwei Werktagen bei dir.\n\nDeine Nachricht:\n„{$subject}“\n" . rs_cut($m['text'], 600) . "\n\nLiebe Grüße\nVictoria Franz aka Westerberg-Chaya\n",
        $to, 'Victoria Franz');
    rs_stats_click('ugc-anfrage');
    return [true, $m];
}
