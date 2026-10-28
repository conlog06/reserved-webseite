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
          'src' => rs_cut((string)preg_replace('/[^\w.\-: \/]/u', '', (string)($in['src'] ?? '')), 60) ?: 'direkt', 'seen' => false, 'page' => ($in['seite'] ?? '') === 'model' ? 'model' : 'ugc'];
    if ($m['name'] === '') return [false, 'Wie heißt du?'];
    if (!filter_var($m['email'], FILTER_VALIDATE_EMAIL)) return [false, 'Bitte gib eine gültige E-Mail-Adresse an.'];
    if (mb_strlen($m['text']) < 10) return [false, 'Schreib bitte kurz, worum es geht.'];
    if (empty($in['consent'])) return [false, 'Bitte bestätige den Datenschutz-Hinweis.'];
    rs_locked('ugc-anfragen', function () use ($m) { $l = rs_ugc_msgs(); $l[] = $m; rs_write('ugc-anfragen', array_slice($l, -500)); });
    $to = filter_var($U['email'], FILTER_VALIDATE_EMAIL) ? $U['email'] : $c['site']['email'];
    $where = $m['page'] === 'model' ? 'das Model-Portfolio' : 'die UGC-Seite';
    $subject = $m['betreff'] !== '' ? $m['betreff'] : 'Anfrage über ' . $where;
    rs_mail($to, $subject . ' – ' . ($m['firma'] ?: $m['name']),
        "Neue Anfrage über $where:\n\nVon: {$m['name']} <{$m['email']}>\n" . ($m['firma'] !== '' ? ($m['page'] === 'model' ? 'Firma' : 'Marke') . ": {$m['firma']}\n" : '') . "Betreff: $subject\n\n{$m['text']}\n\n—\nEinfach auf diese Mail antworten, um {$m['name']} zu schreiben.\nHerkunft: {$m['src']}\n",
        $m['email'], $m['name']);
    $first = explode(' ', $m['name'])[0];
    rs_mail($m['email'], 'Danke für deine Nachricht', "Hallo $first,\n\ndanke für deine Nachricht – sie ist angekommen! Ich melde mich in der Regel innerhalb von zwei Werktagen bei dir.\n\nDeine Nachricht:\n„{$subject}“\n" . rs_cut($m['text'], 600) . "\n\nLiebe Grüße\n" . ($m['page'] === 'model' ? 'Victoria Franz' : 'Victoria Franz aka Westerberg-Chaya') . "\n",
        $to, 'Victoria Franz');
    rs_stats_click($m['page'] === 'model' ? 'model-anfrage' : 'ugc-anfrage');
    return [true, $m];
}

/* ==========================================================================
   Model-Portfolio: Fotos hochladen (verkleinert auf max. 1400 px, JPG)
   ========================================================================== */
function rs_model_store_upload(string $tmp): string {
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return '';
    $id = bin2hex(random_bytes(5));
    $dir = rs_root() . '/assets/img';
    $data = (string)file_get_contents($tmp);
    if (!function_exists('imagecreatefromstring') || !($im = @imagecreatefromstring($data))) {
        $rel = 'assets/img/model-up-' . $id . '.' . [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
        return @copy($tmp, rs_root() . '/' . $rel) ? $rel : '';
    }
    $w = imagesx($im); $h = imagesy($im);
    $bg = imagecreatetruecolor($w, $h); imagefill($bg, 0, 0, imagecolorallocate($bg, 244, 241, 234));
    imagecopy($bg, $im, 0, 0, 0, 0, $w, $h); imagedestroy($im); $im = $bg;
    /* Handyfotos: Ausrichtung aus EXIF übernehmen */
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($tmp)['Orientation'] ?? 1);
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg) { $r = imagerotate($im, $deg, 0); if ($r) { imagedestroy($im); $im = $r; $w = imagesx($im); $h = imagesy($im); } }
    }
    $max = 1400;
    if (max($w, $h) > $max) { $s = $max / max($w, $h); $nw = (int)round($w * $s); $nh = (int)round($h * $s); $d = imagecreatetruecolor($nw, $nh); imagecopyresampled($d, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im); $im = $d; }
    imageinterlace($im, true);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $rel = 'assets/img/model-up-' . $id . '.jpg';
    $ok = imagejpeg($im, rs_root() . '/' . $rel, 84); imagedestroy($im);
    @chmod(rs_root() . '/' . $rel, 0644);
    return $ok ? $rel : '';
}
function rs_model_delete_file(string $rel): void {
    if (preg_match('~^assets/img/model-up-[a-f0-9]+\.(jpg|png|webp)$~', $rel)) @unlink(rs_root() . '/' . $rel);
}
