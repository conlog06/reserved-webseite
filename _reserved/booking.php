<?php
/* ==========================================================================
   reserved.social – Termine (Victoria schlägt vor, die Person bestätigt)
   1. Victoria legt im Admin einen Termin an (Art, Datum, Uhrzeit, Ort)
   2. Die Person bekommt eine anpassbare E-Mail mit Kalender-Datei (.ics)
      und einem Link zu /termin/?t=…
   3. Dort bestätigt sie („Ja, passt“) oder sagt ab (mit Nachricht)
   4. Victoria bekommt eine Mail und sieht den Status im Admin
   Kein externer Dienst, keine Kosten.
   ========================================================================== */
declare(strict_types=1);

const RS_BK_STATUS = ['offen' => 'Wartet auf Bestätigung', 'bestaetigt' => 'Bestätigt', 'abgelehnt' => 'Passt nicht', 'abgesagt' => 'Abgesagt'];

function rs_bookings(): array { return rs_read('bookings', []); }
function rs_bookings_save(array $b): void {
    usort($b, fn($x, $y) => $x['start'] <=> $y['start']);
    rs_write('bookings', array_values($b));
}
function rs_booking_types(?array $B = null): array {
    $B = $B ?? rs_config()['booking'];
    return array_values(array_filter((array)$B['types'], fn($t) => trim((string)($t['name'] ?? '')) !== '' && (int)($t['min'] ?? 0) > 0));
}
function rs_booking_type(string $id, ?array $B = null): ?array {
    foreach (rs_booking_types($B) as $t) if ($t['id'] === $id) return $t;
    return null;
}
function rs_booking_find(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    foreach (rs_bookings() as $b) if (hash_equals($b['token'], $token)) return $b;
    return null;
}
function rs_booking_update(string $id, callable $fn): ?array {
    return rs_locked('bookings', function () use ($id, $fn) {
        $all = rs_bookings(); $hit = null;
        foreach ($all as &$b) if ($b['id'] === $id || hash_equals($b['token'], $id)) { $fn($b); $hit = $b; }
        unset($b);
        if ($hit) rs_bookings_save($all);
        return $hit;
    });
}
function rs_booking_when(array $b): string {
    return rs_date_de((int)$b['start'], true, false) . ', ' . date('H:i', (int)$b['start']) . '–' . date('H:i', (int)$b['end']) . ' Uhr';
}
function rs_booking_link(array $b): string { return rs_abs_url('termin/?t=' . $b['token']); }

/** Platzhalter in Betreff/Text ersetzen */
function rs_booking_fill(string $tpl, array $b): string {
    $min = (int)round(((int)$b['end'] - (int)$b['start']) / 60);
    $map = ['{vorname}' => explode(' ', trim($b['name']))[0], '{name}' => $b['name'], '{terminart}' => $b['type_name'],
            '{datum}' => rs_date_de((int)$b['start'], true, false), '{uhrzeit}' => date('H:i', (int)$b['start']), '{bis}' => date('H:i', (int)$b['end']),
            '{dauer}' => (string)$min, '{ort}' => (string)$b['where'], '{link}' => rs_booking_link($b), '{nachricht}' => (string)($b['note'] ?? '')];
    $out = strtr($tpl, $map);
    $out = (string)preg_replace("/\n{3,}/", "\n\n", $out);
    return trim($out);
}

/** Kalender-Datei (.ics) – funktioniert mit iPhone, Android, Outlook und Google */
function rs_booking_ics(array $b, bool $forOwner = false, bool $cancel = false): string {
    $esc = fn($s) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], (string)$s);
    $utc = fn($t) => gmdate('Ymd\THis\Z', (int)$t);
    $title = $forOwner ? $b['type_name'] . ' – ' . $b['name'] : $b['type_name'] . ' mit Victoria (reserved.social)';
    $desc = $forOwner ? $b['name'] . ' · ' . $b['email'] : "Bestätigen oder absagen: " . rs_booking_link($b);
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//reserved.social//Termin//DE', 'CALSCALE:GREGORIAN', 'METHOD:' . ($cancel ? 'CANCEL' : 'PUBLISH'), 'BEGIN:VEVENT',
        'UID:' . $b['id'] . '@reserved.social', 'SEQUENCE:' . (int)($b['seq'] ?? 0), 'DTSTAMP:' . $utc(time()), 'DTSTART:' . $utc($b['start']), 'DTEND:' . $utc($b['end']),
        'SUMMARY:' . $esc($title), 'DESCRIPTION:' . $esc($desc), 'STATUS:' . ($cancel ? 'CANCELLED' : 'CONFIRMED')];
    if (($b['where'] ?? '') !== '') $lines[] = 'LOCATION:' . $esc($b['where']);
    if (!$cancel) $lines = array_merge($lines, ['BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($title), 'END:VALARM']);
    $lines = array_merge($lines, ['END:VEVENT', 'END:VCALENDAR']);
    $fold = fn($l) => implode("\r\n ", array_map(fn($p) => implode('', $p), array_chunk(mb_str_split($l), 60)));
    return implode("\r\n", array_map($fold, $lines)) . "\r\n";
}
function rs_booking_ics_att(array $b, bool $cancel = false): array {
    return [['name' => $cancel ? 'termin-abgesagt.ics' : 'termin.ics', 'type' => 'text/calendar; charset=utf-8; method=' . ($cancel ? 'CANCEL' : 'PUBLISH'), 'data' => rs_booking_ics($b, false, $cancel)]];
}

/** Vorschlag-Mail an die Person (mit .ics im Anhang); der Bestätigungslink wird ergänzt, falls er im Text fehlt */
function rs_booking_send(array $b): bool {
    $text = (string)($b['mail_text'] ?? '');
    if (!str_contains($text, '{link}') && !str_contains($text, rs_booking_link($b))) $text .= "\n\nBitte bestätige den Termin hier: {link}";
    return rs_mail($b['email'], rs_booking_fill((string)($b['mail_subject'] ?? 'Terminvorschlag'), $b), rs_booking_fill($text, $b),
        rs_config()['site']['email'], 'Victoria – reserved.social', rs_booking_ics_att($b));
}

/** Neuer Termin (aus dem Admin). Gibt [ok, Termin|Fehler, Überschneidung|null] zurück. */
function rs_booking_create(array $in): array {
    $B = rs_config()['booking'];
    $name = str_replace(["\r", "\n"], ' ', rs_cut(trim(strip_tags((string)($in['name'] ?? ''))), 80));
    $email = rs_cut(trim((string)($in['email'] ?? '')), 200);
    if ($name === '') return [false, 'Bitte einen Namen eintragen.', null];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Bitte eine gültige E-Mail-Adresse eintragen.', null];
    $start = rs_ts(trim((string)($in['date'] ?? '')) . 'T' . trim((string)($in['time'] ?? '')));
    if (!$start) return [false, 'Bitte Datum und Uhrzeit wählen.', null];
    if ($start < time() - 3600) return [false, 'Der Termin liegt in der Vergangenheit.', null];
    $type = rs_booking_type((string)($in['art'] ?? ''), $B);
    $min = max(10, min(480, (int)($in['min'] ?? ($type['min'] ?? 30))));
    $tname = rs_cut(trim((string)($in['type_name'] ?? '')), 60) ?: ($type['name'] ?? 'Termin');
    $b = ['id' => bin2hex(random_bytes(6)), 'token' => bin2hex(random_bytes(16)), 'type' => $type['id'] ?? 'individuell', 'type_name' => $tname,
          'where' => rs_cut(trim((string)($in['where'] ?? '')), 200), 'start' => $start, 'end' => $start + $min * 60, 'name' => $name, 'email' => $email,
          'note' => rs_cut(trim(str_replace("\r", '', (string)($in['note'] ?? ''))), 1500), 'lead' => preg_replace('/[^a-f0-9]/', '', (string)($in['lead'] ?? '')),
          'mail_subject' => rs_cut(trim((string)($in['mail_subject'] ?? $B['mail_subject'])), 200), 'mail_text' => rs_cut(str_replace("\r", '', (string)($in['mail_text'] ?? $B['mail_text'])), 5000),
          'status' => 'offen', 'created' => time(), 'sent' => 0, 'seq' => 0, 'answer' => '', 'answered' => 0];
    $clash = null;
    foreach (rs_bookings() as $x) if (in_array($x['status'], ['offen', 'bestaetigt'], true) && $b['start'] < $x['end'] && $b['end'] > $x['start']) $clash = $x;
    rs_locked('bookings', function () use ($b) { $all = rs_bookings(); $all[] = $b; rs_bookings_save($all); });
    if (rs_booking_send($b)) $b = rs_booking_update($b['id'], function (&$x) { $x['sent'] = time(); }) ?? $b;
    return [true, $b, $clash];
}

/** Antwort der Person: ja = bestätigt, nein = passt nicht (mit Nachricht) */
function rs_booking_answer(string $token, bool $yes, string $msg = ''): ?array {
    $msg = rs_cut(trim(strip_tags(str_replace("\r", '', $msg))), 1500);
    $b = rs_booking_find($token);
    if (!$b || in_array($b['status'], ['abgesagt'], true) || (int)$b['end'] < time()) return null;
    $b = rs_booking_update($b['id'], function (&$x) use ($yes, $msg) { $x['status'] = $yes ? 'bestaetigt' : 'abgelehnt'; $x['answer'] = $msg; $x['answered'] = time(); });
    $c = rs_config(); $first = explode(' ', $b['name'])[0];
    if ($yes) {
        rs_mail($b['email'], 'Bestätigt: ' . $b['type_name'] . ' am ' . date('d.m.', (int)$b['start']),
            "Hallo $first,\n\ndanke für deine Bestätigung – ich freue mich auf unser Gespräch!\n\n{$b['type_name']}\n" . rs_booking_when($b) . ($b['where'] !== '' ? "\n{$b['where']}" : '')
            . "\n\nDen Termin findest du im Anhang für deinen Kalender. Falls doch etwas dazwischenkommt, sag hier kurz Bescheid:\n" . rs_booking_link($b) . "\n\nLiebe Grüße\nVictoria\nreserved.social\n",
            $c['site']['email'], 'Victoria – reserved.social', rs_booking_ics_att($b));
    }
    if (!empty($c['booking']['notify'])) rs_mail($c['site']['email'], ($yes ? '✓ Bestätigt: ' : '✗ Passt nicht: ') . $b['type_name'] . ' – ' . $b['name'],
        $b['name'] . ($yes ? " hat den Termin bestätigt:\n\n" : " kann zu diesem Termin nicht:\n\n") . $b['type_name'] . "\n" . rs_booking_when($b) . "\n"
        . ($msg !== '' ? "\nNachricht:\n$msg\n" : '') . "\nAlle Termine: " . rs_abs_url('admin/?tab=termine') . "\n", $b['email'], $b['name']);
    rs_stats_click($yes ? 'termin-bestaetigt' : 'termin-abgelehnt');
    return $b;
}

/** Absagen durch Victoria – die Person bekommt eine Mail, der Kalendereintrag wird entfernt */
function rs_booking_cancel(string $id, string $msg = ''): ?array {
    $b = rs_booking_update($id, function (&$x) { $x['status'] = 'abgesagt'; $x['cancelled'] = time(); $x['seq'] = (int)($x['seq'] ?? 0) + 1; });
    if (!$b) return null;
    $first = explode(' ', $b['name'])[0];
    rs_mail($b['email'], 'Abgesagt: ' . $b['type_name'] . ' am ' . date('d.m.', (int)$b['start']),
        "Hallo $first,\n\nleider muss ich unseren Termin absagen:\n\n{$b['type_name']}\n" . rs_booking_when($b) . "\n\n" . ($msg !== '' ? "$msg\n\n" : '')
        . "Ich melde mich mit einem neuen Vorschlag. Entschuldige bitte!\n\nLiebe Grüße\nVictoria\nreserved.social\n",
        rs_config()['site']['email'], 'Victoria – reserved.social', rs_booking_ics_att($b, true));
    return $b;
}

/** Verschieben: neue Zeit, Status wieder „offen“, Mail mit neuem Kalendereintrag */
function rs_booking_move(string $id, string $date, string $time): ?array {
    $start = rs_ts(trim($date) . 'T' . trim($time));
    if (!$start) return null;
    $b = rs_booking_update($id, function (&$x) use ($start) {
        $len = (int)$x['end'] - (int)$x['start'];
        $x['start'] = $start; $x['end'] = $start + $len; $x['status'] = 'offen'; $x['answer'] = ''; $x['answered'] = 0; $x['seq'] = (int)($x['seq'] ?? 0) + 1;
        $x['mail_subject'] = 'Neuer Terminvorschlag: {terminart} am {datum}';
    });
    if ($b && rs_booking_send($b)) $b = rs_booking_update($b['id'], function (&$x) { $x['sent'] = time(); });
    return $b;
}
