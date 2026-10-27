<?php
/* ==========================================================================
   reserved.social – eigene Terminbuchung (/termin/)
   - Terminarten (z. B. Erstgespräch, Strategie-Call) mit Dauer
   - Wochenplan mit Zeitfenstern, gesperrte Tage, Vorlauf, Puffer
   - Buchung → Bestätigung per Mail (mit Kalender-Datei + Absage-Link), Mail an Victoria
   Kein externer Dienst, keine Kosten.
   ========================================================================== */
declare(strict_types=1);

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
/** „09:00-12:00, 14:00-17:30“ → [[540, 720], [840, 1050]] (Minuten ab Mitternacht) */
function rs_booking_ranges(string $s): array {
    $out = [];
    foreach (preg_split('/[,;\n]+/', $s) ?: [] as $part) {
        if (preg_match('/(\d{1,2})[:.](\d{2})\s*(?:-|–|bis)\s*(\d{1,2})[:.](\d{2})/u', $part, $m)) {
            $a = (int)$m[1] * 60 + (int)$m[2]; $b = (int)$m[3] * 60 + (int)$m[4];
            if ($b > $a && $b <= 24 * 60) $out[] = [$a, $b];
        }
    }
    return $out;
}
/** Gesperrte Tage: „24.12.2026“ oder „2026-12-24“, Zeiträume mit „bis“ oder „-“ */
function rs_booking_blocked(string $s): array {
    $days = [];
    $d = fn($x) => ($t = DateTime::createFromFormat('!d.m.Y', trim($x)) ?: DateTime::createFromFormat('!Y-m-d', trim($x))) ? $t : null;
    foreach (preg_split('/\R/', $s) ?: [] as $line) {
        $line = trim(preg_replace('/#.*/', '', $line) ?? '');
        if ($line === '') continue;
        $p = preg_split('/\s*(?:bis|–|—|\s-\s)\s*/u', $line);
        $a = $d($p[0] ?? ''); $b = isset($p[1]) ? $d($p[1]) : $a;
        if (!$a || !$b) continue;
        for ($i = 0, $x = clone $a; $x <= $b && $i < 400; $x->modify('+1 day'), $i++) $days[$x->format('Y-m-d')] = true;
    }
    return $days;
}
function rs_booking_daylabel(int $ts): array {
    $t = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']; $m = ['', 'Jan', 'Feb', 'März', 'Apr', 'Mai', 'Juni', 'Juli', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    return [$t[(int)date('w', $ts)], date('j', $ts), $m[(int)date('n', $ts)]];
}

/** Freie Termine für eine Terminart: [ ['date' => 'Y-m-d', 'wd' => 'Mo', 'd' => '3', 'm' => 'Nov', 'slots' => ['09:00', …]], … ] */
function rs_booking_slots(string $typeId, ?array $bookings = null): array {
    $B = rs_config()['booking'];
    $type = rs_booking_type($typeId, $B);
    if (!$type || empty($B['on'])) return [];
    $dur = (int)$type['min']; $step = max(10, (int)$B['step']); $buf = max(0, (int)$B['buffer']);
    $earliest = time() + max(0, (int)$B['notice']) * 3600;
    $blocked = rs_booking_blocked((string)$B['blocked']);
    $bookings = array_filter($bookings ?? rs_bookings(), fn($b) => ($b['status'] ?? 'ok') === 'ok');
    $perDay = [];
    foreach ($bookings as $b) $perDay[date('Y-m-d', (int)$b['start'])] = ($perDay[date('Y-m-d', (int)$b['start'])] ?? 0) + 1;
    $out = [];
    $day = new DateTime('today');
    for ($i = 0; $i <= max(1, (int)$B['days']); $i++, $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        if (isset($blocked[$key])) continue;
        if ((int)$B['max_per_day'] > 0 && ($perDay[$key] ?? 0) >= (int)$B['max_per_day']) continue;
        $ranges = rs_booking_ranges((string)($B['hours'][$day->format('N')] ?? ''));
        $slots = [];
        foreach ($ranges as [$a, $z]) {
            for ($m = $a; $m + $dur <= $z; $m += $step) {
                $start = (clone $day)->setTime(intdiv($m, 60), $m % 60)->getTimestamp(); $end = $start + $dur * 60;
                if ($start < $earliest) continue;
                foreach ($bookings as $b) if ($start < (int)$b['end'] + $buf * 60 && $end + $buf * 60 > (int)$b['start']) continue 2;
                $slots[] = date('H:i', $start);
            }
        }
        if ($slots) { [$wd, $d, $mo] = rs_booking_daylabel($day->getTimestamp()); $out[] = ['date' => $key, 'wd' => $wd, 'd' => $d, 'm' => $mo, 'slots' => $slots]; }
    }
    return $out;
}

function rs_booking_find(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    foreach (rs_bookings() as $b) if (hash_equals($b['token'], $token)) return $b;
    return null;
}
function rs_booking_when(array $b): string {
    return rs_date_de((int)$b['start'], true, false) . ', ' . date('H:i', (int)$b['start']) . '–' . date('H:i', (int)$b['end']) . ' Uhr';
}
function rs_booking_links(array $b): array {
    return ['ics' => rs_abs_url('_reserved/api.php?a=termin_ics&t=' . $b['token']), 'cancel' => rs_abs_url('_reserved/api.php?a=termin_absagen&t=' . $b['token'])];
}

/** Buchen: prüft den Termin unter Sperre erneut, speichert und verschickt die Mails */
function rs_booking_create(array $in): array {
    $c = rs_config(); $B = $c['booking'];
    if (empty($B['on'])) return [false, 'Die Terminbuchung ist gerade nicht verfügbar.'];
    $type = rs_booking_type((string)($in['art'] ?? ''), $B);
    if (!$type) return [false, 'Bitte wähle aus, worum es gehen soll.'];
    $date = (string)($in['date'] ?? ''); $time = (string)($in['time'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) return [false, 'Bitte wähle einen Tag und eine Uhrzeit.'];
    $name = str_replace(["\r", "\n"], ' ', rs_cut(trim(strip_tags((string)($in['name'] ?? ''))), 80));
    $email = rs_cut(trim((string)($in['email'] ?? '')), 200);
    if ($name === '') return [false, 'Wie heißt du?'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Bitte gib eine gültige E-Mail-Adresse an.'];
    $phone = rs_cut(preg_replace('/[^\d+ ()\/-]/', '', (string)($in['phone'] ?? '')) ?? '', 40);
    $firma = rs_cut(str_replace(["\r", "\n"], ' ', trim(strip_tags((string)($in['firma'] ?? '')))), 120);
    $note = rs_cut(trim(strip_tags(str_replace("\r", '', (string)($in['note'] ?? '')))), 1500);
    $src = rs_cut((string)preg_replace('/[^\w.\-: \/]/u', '', (string)($in['src'] ?? '')), 60) ?: 'direkt';

    $res = rs_locked('bookings', function () use ($type, $date, $time, $name, $email, $phone, $firma, $note, $src) {
        $all = rs_bookings();
        $free = false;
        foreach (rs_booking_slots($type['id'], $all) as $d) if ($d['date'] === $date && in_array($time, $d['slots'], true)) $free = true;
        if (!$free) return null;
        $start = (new DateTime($date . ' ' . $time))->getTimestamp();
        $b = ['id' => bin2hex(random_bytes(6)), 'token' => bin2hex(random_bytes(16)), 'type' => $type['id'], 'type_name' => $type['name'], 'where' => (string)($type['where'] ?? ''),
              'start' => $start, 'end' => $start + (int)$type['min'] * 60, 'name' => $name, 'email' => $email, 'phone' => $phone, 'firma' => $firma, 'note' => $note,
              'src' => $src, 'created' => time(), 'status' => 'ok'];
        $all[] = $b; rs_bookings_save($all);
        return $b;
    });
    if (!$res) return [false, 'Dieser Termin ist leider gerade vergeben worden. Bitte such dir einen anderen aus.'];

    $L = rs_booking_links($res); $first = explode(' ', $name)[0];
    $where = $res['where'] !== '' ? "Wo: {$res['where']}\n" : '';
    rs_mail($email, 'Dein Termin: ' . $res['type_name'] . ' am ' . date('d.m.', (int)$res['start']),
        "Hallo $first,\n\ndein Termin ist gebucht – ich freue mich auf dich!\n\n{$res['type_name']}\n" . rs_booking_when($res) . "\n$where\n"
        . ($B['confirm_text'] !== '' ? rs_plain((string)$B['confirm_text']) . "\n\n" : '')
        . "In deinen Kalender eintragen: {$L['ics']}\n\nDu kannst doch nicht? Hier kannst du den Termin absagen: {$L['cancel']}\n\nLiebe Grüße\nVictoria\nreserved.social – Social Media für lokale Unternehmen\n",
        $c['site']['email'], 'Victoria – reserved.social');
    if (!empty($B['notify'])) rs_mail($c['site']['email'], 'Neuer Termin: ' . $res['type_name'] . ' – ' . $name,
        "Neuer Termin über die Webseite:\n\n{$res['type_name']}\n" . rs_booking_when($res) . "\n\nName: $name\nE-Mail: $email\n" . ($phone !== '' ? "Telefon: $phone\n" : '') . ($firma !== '' ? "Unternehmen/Instagram: $firma\n" : '')
        . ($note !== '' ? "\nNachricht:\n$note\n" : '') . "\nHerkunft: $src\n\nIn deinen Kalender: {$L['ics']}\nAlle Termine: " . rs_abs_url('admin/?tab=termine') . "\n", $email, $name);
    rs_stats_click('termin-gebucht');
    return [true, $res];
}

function rs_booking_cancel(string $token, string $by = 'kunde'): ?array {
    $hit = rs_locked('bookings', function () use ($token, $by) {
        $all = rs_bookings(); $hit = null;
        foreach ($all as &$b) if (hash_equals($b['token'], $token) && $b['status'] === 'ok') { $b['status'] = 'cancelled'; $b['cancelled'] = time(); $b['cancelled_by'] = $by; $hit = $b; }
        unset($b);
        if ($hit) rs_bookings_save($all);
        return $hit;
    });
    if (!$hit) return null;
    $c = rs_config();
    if ($by === 'kunde') rs_mail($c['site']['email'], 'Abgesagt: ' . $hit['type_name'] . ' – ' . $hit['name'], "{$hit['name']} hat den Termin abgesagt:\n\n{$hit['type_name']}\n" . rs_booking_when($hit) . "\n\nDer Termin ist wieder frei.\n", $hit['email'], $hit['name']);
    else rs_mail($hit['email'], 'Dein Termin am ' . date('d.m.', (int)$hit['start']) . ' fällt leider aus', "Hallo " . explode(' ', $hit['name'])[0] . ",\n\nleider muss ich unseren Termin absagen:\n\n{$hit['type_name']}\n" . rs_booking_when($hit) . "\n\nSuch dir gern einen neuen Termin aus: " . rs_abs_url('termin/?art=' . $hit['type']) . "\n\nEntschuldige bitte und liebe Grüße\nVictoria\nreserved.social\n", $c['site']['email'], 'Victoria – reserved.social');
    rs_stats_click('termin-abgesagt');
    return $hit;
}

/** Kalender-Datei (.ics) für Outlook, Apple- und Google-Kalender */
function rs_booking_ics(array $b, bool $forOwner = false): string {
    $esc = fn($s) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], (string)$s);
    $utc = fn($t) => gmdate('Ymd\THis\Z', (int)$t);
    $title = $forOwner ? $b['type_name'] . ' – ' . $b['name'] : $b['type_name'] . ' mit Victoria (reserved.social)';
    $desc = $forOwner ? "{$b['name']} · {$b['email']}" . ($b['phone'] ? " · {$b['phone']}" : '') . ($b['note'] ? "\n\n{$b['note']}" : '') : 'Absagen: ' . rs_booking_links($b)['cancel'];
    $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//reserved.social//Termin//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
        'UID:' . $b['id'] . '@reserved.social', 'DTSTAMP:' . $utc(time()), 'DTSTART:' . $utc($b['start']), 'DTEND:' . $utc($b['end']),
        'SUMMARY:' . $esc($title), 'DESCRIPTION:' . $esc($desc)];
    if (($b['where'] ?? '') !== '') $lines[] = 'LOCATION:' . $esc($b['where']);
    $lines = array_merge($lines, ['BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($title), 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR']);
    $fold = fn($l) => implode("\r\n ", array_map(fn($p) => implode('', $p), array_chunk(mb_str_split($l), 60)));
    return implode("\r\n", array_map($fold, $lines)) . "\r\n";
}
