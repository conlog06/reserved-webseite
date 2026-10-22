<?php
/* ==========================================================================
   Reserved. – Instagram-Anbindung (Instagram API mit Instagram-Login)
   - Holt Profil (Follower, Beiträge) und die neuesten Beiträge.
   - Bilder werden auf den eigenen Server kopiert (assets/ig/): schneller,
     und Besucher laden nichts von Meta-Servern (Datenschutz).
   - Aktualisiert sich alle 30 Minuten selbst, nach dem Ausliefern der Seite.
   - Der Zugangsschlüssel (60 Tage gültig) wird automatisch verlängert.
   ========================================================================== */

declare(strict_types=1);

const RS_IG_API = 'https://graph.instagram.com';
const RS_IG_VERSION = 'v26.0';
const RS_IG_DIR = 'assets/ig';
const RS_IG_INTERVAL = 1800; // Sekunden zwischen zwei Abgleichen

function rs_ig_http(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
                                CURLOPT_USERAGENT => 'Reserved-Website/1.0']);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, $body === false ? '' : (string)$body, $err];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true, 'header' => "User-Agent: Reserved-Website/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
    return [$code, $body === false ? '' : (string)$body, $body === false ? 'Verbindung fehlgeschlagen' : ''];
}

function rs_ig_api(string $path, array $params, string $token): array {
    $params['access_token'] = $token;
    [$code, $body, $err] = rs_ig_http(RS_IG_API . '/' . RS_IG_VERSION . '/' . ltrim($path, '/') . '?' . http_build_query($params));
    $json = json_decode($body, true);
    if (!is_array($json)) throw new RuntimeException('Instagram nicht erreichbar' . ($err ? " ($err)" : " (HTTP $code)"));
    if (isset($json['error'])) {
        $msg = (string)($json['error']['message'] ?? 'Unbekannter Fehler');
        if (in_array((int)($json['error']['code'] ?? 0), [190, 102], true)) $msg = 'Zugangsschlüssel ungültig oder abgelaufen – bitte im Admin-Bereich neu eintragen. (' . $msg . ')';
        throw new RuntimeException($msg);
    }
    return $json;
}

/** Zugangsschlüssel verlängern (frühestens 24 h nach Ausstellung, spätestens vor Ablauf) */
function rs_ig_refresh_token(array &$c): void {
    $ig = &$c['ig'];
    if ($ig['token'] === '') return;
    $age = time() - (int)$ig['token_refreshed'];
    if ($ig['token_refreshed'] && $age < 7 * 86400) return; // höchstens einmal pro Woche
    if (!$ig['token_refreshed'] && $ig['token_expires'] && $ig['token_expires'] - time() > 50 * 86400) return; // frisch eingetragen
    [$code, $body] = rs_ig_http(RS_IG_API . '/refresh_access_token?' . http_build_query(['grant_type' => 'ig_refresh_token', 'access_token' => $ig['token']]));
    $json = json_decode($body, true);
    if (is_array($json) && !empty($json['access_token'])) {
        $ig['token'] = (string)$json['access_token'];
        $ig['token_expires'] = time() + (int)($json['expires_in'] ?? 5184000);
        $ig['token_refreshed'] = time();
    }
}

/** Bild herunterladen, auf max. 1080 px verkleinern und als JPG speichern */
function rs_ig_store_image(string $url, string $name, int $max = 1080): string {
    $rel = RS_IG_DIR . '/' . preg_replace('/[^a-z0-9_-]/i', '', $name) . '.jpg';
    $abs = rs_root() . '/' . $rel;
    if (is_file($abs) && filesize($abs) > 0) return $rel;
    if ($url === '') return '';
    if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0755, true);
    [$code, $data] = rs_ig_http($url);
    if ($code !== 200 || $data === '') return '';
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($data))) {
        $w = imagesx($im); $h = imagesy($im);
        if ($w > $max) {
            $nh = (int)round($h * $max / $w);
            $dst = imagecreatetruecolor($max, $nh);
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $max, $nh, $w, $h);
            imagedestroy($im); $im = $dst;
        }
        imageinterlace($im, true);
        $ok = imagejpeg($im, $abs, 82);
        // kleinere Variante fürs Handy (spart Datenvolumen)
        if ($max > 600 && imagesx($im) > 540) {
            $sh = (int)round(imagesy($im) * 540 / imagesx($im));
            $sm = imagecreatetruecolor(540, $sh);
            imagecopyresampled($sm, $im, 0, 0, 0, 0, 540, $sh, imagesx($im), imagesy($im));
            imageinterlace($sm, true);
            imagejpeg($sm, substr($abs, 0, -4) . '-540.jpg', 80);
            imagedestroy($sm);
        }
        imagedestroy($im);
        if (!$ok) return '';
    } elseif (@file_put_contents($abs, $data) === false) return '';
    @chmod($abs, 0644);
    return $rel;
}

/** Profil und Beiträge abgleichen */
function rs_ig_sync(bool $force = false): array {
    return rs_locked('instagram', function () use ($force) {
        $c = rs_config();
        if ($c['ig']['token'] === '') return ['ok' => false, 'error' => 'Kein Zugangsschlüssel eingetragen.'];
        if (!$force && time() - (int)$c['ig']['last_sync'] < RS_IG_INTERVAL) return ['ok' => true, 'skipped' => true];
        try {
            rs_ig_refresh_token($c);
            $token = $c['ig']['token'];
            try {
                $p = rs_ig_api('me', ['fields' => 'user_id,username,name,profile_picture_url,followers_count,follows_count,media_count'], $token);
            } catch (RuntimeException $e) {
                if (strpos($e->getMessage(), 'Zugangsschlüssel') !== false) throw $e;
                $p = rs_ig_api('me', ['fields' => 'user_id,username,media_count'], $token); // ältere Konten / eingeschränkte Felder
            }
            $limit = max(12, min(30, (int)$c['ig']['count']));
            $m = rs_ig_api('me/media', ['fields' => 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count,children{id,media_type,media_url,thumbnail_url}', 'limit' => $limit], $token);

            $keep = [];
            $pic = !empty($p['profile_picture_url']) ? rs_ig_store_image((string)$p['profile_picture_url'], 'profil-' . substr(md5((string)$p['profile_picture_url']), 0, 8), 320) : '';
            if ($pic) $keep[] = $pic;
            $posts = [];
            foreach ($m['data'] ?? [] as $it) {
                $type = (string)($it['media_type'] ?? 'IMAGE');
                $cover = $type === 'VIDEO' ? (string)($it['thumbnail_url'] ?? '') : (string)($it['media_url'] ?? '');
                $img = rs_ig_store_image($cover, (string)$it['id']);
                if ($img === '') continue;
                $keep[] = $img;
                $children = [];
                if ($type === 'CAROUSEL_ALBUM') {
                    foreach (array_slice($it['children']['data'] ?? [], 0, 10) as $ch) {
                        $ct = (string)($ch['media_type'] ?? 'IMAGE');
                        $ci = rs_ig_store_image($ct === 'VIDEO' ? (string)($ch['thumbnail_url'] ?? '') : (string)($ch['media_url'] ?? ''), (string)$ch['id']);
                        if ($ci === '') continue;
                        $keep[] = $ci;
                        $children[] = ['type' => $ct, 'img' => $ci, 'video' => $ct === 'VIDEO' ? (string)($ch['media_url'] ?? '') : ''];
                    }
                }
                $posts[] = [
                    'id' => (string)$it['id'], 'type' => $type, 'reel' => ($it['media_product_type'] ?? '') === 'REELS',
                    'caption' => mb_substr((string)($it['caption'] ?? ''), 0, 2200), 'permalink' => (string)($it['permalink'] ?? ''),
                    'ts' => strtotime((string)($it['timestamp'] ?? 'now')) ?: time(),
                    'likes' => isset($it['like_count']) ? (int)$it['like_count'] : null, 'comments' => isset($it['comments_count']) ? (int)$it['comments_count'] : null,
                    'img' => $img, 'video' => $type === 'VIDEO' ? (string)($it['media_url'] ?? '') : '', 'children' => $children,
                ];
            }
            $profile = ['username' => (string)($p['username'] ?? $c['site']['instagram']), 'name' => (string)($p['name'] ?? ''), 'pic' => $pic,
                        'followers' => isset($p['followers_count']) ? (int)$p['followers_count'] : null, 'follows' => isset($p['follows_count']) ? (int)$p['follows_count'] : null,
                        'media' => isset($p['media_count']) ? (int)$p['media_count'] : count($posts)];
            rs_write('instagram', ['profile' => $profile, 'posts' => $posts, 'synced' => time()]);

            // Verlauf für die Statistik (ein Eintrag pro Tag)
            $hist = rs_read('ig-history', []);
            $likes = array_sum(array_map(fn($x) => (int)($x['likes'] ?? 0) + (int)($x['comments'] ?? 0), array_slice($posts, 0, 12)));
            $hist[date('Y-m-d')] = ['f' => $profile['followers'], 'm' => $profile['media'], 'i' => $likes, 'n' => min(12, count($posts))];
            ksort($hist);
            if (count($hist) > 400) $hist = array_slice($hist, -400, null, true);
            rs_write('ig-history', $hist);

            // Nicht mehr benötigte Bilder löschen
            foreach (glob(rs_root() . '/' . RS_IG_DIR . '/*.jpg') ?: [] as $f) {
                $base = RS_IG_DIR . '/' . preg_replace('/-540\.jpg$/', '.jpg', basename($f));
                if (!in_array($base, $keep, true)) @unlink($f);
            }
            $c = rs_config_merge_ig($c, ['last_sync' => time(), 'last_error' => '']);
            if ($profile['username'] !== '') $c['site']['instagram'] = $profile['username'];
            rs_save_config($c);
            return ['ok' => true, 'count' => count($posts)];
        } catch (Throwable $e) {
            $c = rs_config_merge_ig($c, ['last_sync' => time(), 'last_error' => $e->getMessage()]);
            rs_save_config($c);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    });
}
function rs_config_merge_ig(array $c, array $set): array {
    $fresh = rs_config();          // Admin könnte inzwischen gespeichert haben
    $fresh['ig'] = array_merge($fresh['ig'], ['token' => $c['ig']['token'], 'token_expires' => $c['ig']['token_expires'], 'token_refreshed' => $c['ig']['token_refreshed']], $set);
    return $fresh;
}

/** Nach dem Ausliefern einer Seite aufrufen */
function rs_ig_maybe_sync(): void {
    $c = rs_config();
    if (!$c['ig']['on'] || $c['ig']['token'] === '') return;
    if (time() - (int)$c['ig']['last_sync'] < RS_IG_INTERVAL) return;
    try { rs_ig_sync(); } catch (Throwable $e) {}
}

/** Daten für die Webseite: echte Beiträge oder null (dann bleibt der statische Feed stehen) */
function rs_ig_data(): ?array {
    $c = rs_config();
    if (!$c['ig']['on'] || $c['ig']['token'] === '') return null;
    $d = rs_read('instagram', []);
    if (empty($d['posts'])) return null;
    $d['posts'] = array_slice($d['posts'], 0, max(3, min(30, (int)$c['ig']['count'])));
    return $d;
}

/** srcset für ein zwischengespeichertes Instagram-Bild (falls eine kleine Variante existiert) */
function rs_ig_srcset(string $rel): string {
    $small = substr($rel, 0, -4) . '-540.jpg';
    if (!is_file(rs_root() . '/' . $small)) return '';
    return ' srcset="' . e(rs_url($small)) . ' 540w, ' . e(rs_url($rel)) . ' 1080w"';
}
