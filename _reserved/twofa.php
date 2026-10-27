<?php
/* ==========================================================================
   reserved.social – Zwei-Faktor-Anmeldung (TOTP, z. B. Google Authenticator,
   Microsoft Authenticator, Apple-Passwörter) + Passwort-Zurücksetzen per Mail
   Notfall: Datei _reserved/data/zwei-faktor.php per FTP löschen → 2FA aus.
   ========================================================================== */
declare(strict_types=1);

function rs_b32_encode(string $bin): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $a[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function rs_b32_decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function rs_totp(string $secret, int $step): string {
    $h = hash_hmac('sha1', pack('N*', 0, $step), rs_b32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Gibt den passenden Zeitschritt zurück (±30 Sekunden Toleranz) oder null */
function rs_totp_check(string $secret, string $code, int $after = 0): ?int {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) { $st = $now + $d; if ($st > $after && hash_equals(rs_totp($secret, $st), $code)) return $st; }
    return null;
}

function rs_2fa(): array { return rs_read('zwei-faktor', []); }
function rs_2fa_on(): bool { $t = rs_2fa(); return !empty($t['on']) && !empty($t['secret']); }
function rs_2fa_uri(string $secret): string {
    return 'otpauth://totp/' . rawurlencode('reserved.social:Admin') . '?secret=' . $secret . '&issuer=' . rawurlencode('reserved.social') . '&digits=6&period=30';
}
/** Code prüfen: App-Code oder einmaliger Notfall-Code. Gibt 'app' | 'backup' | null zurück. */
function rs_2fa_verify(string $code): ?string {
    return rs_locked('zwei-faktor', function () use ($code) {
        $t = rs_2fa(); if (empty($t['on'])) return null;
        if (($st = rs_totp_check($t['secret'], $code, (int)($t['last_step'] ?? 0))) !== null) { $t['last_step'] = $st; rs_write('zwei-faktor', $t); return 'app'; }
        $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        foreach ((array)($t['backup'] ?? []) as $i => $h) if ($norm !== '' && password_verify($norm, $h)) { unset($t['backup'][$i]); $t['backup'] = array_values($t['backup']); rs_write('zwei-faktor', $t); return 'backup'; }
        return null;
    });
}
function rs_2fa_backup_codes(): array {
    $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $codes = [];
    for ($i = 0; $i < 8; $i++) { $s = ''; for ($k = 0; $k < 8; $k++) $s .= $a[random_int(0, strlen($a) - 1)]; $codes[] = substr($s, 0, 4) . '-' . substr($s, 4); }
    return $codes;
}
function rs_2fa_enable(string $secret): array {
    $codes = rs_2fa_backup_codes();
    rs_write('zwei-faktor', ['on' => true, 'secret' => $secret, 'since' => time(), 'last_step' => intdiv(time(), 30), 'trust_salt' => bin2hex(random_bytes(8)),
        'backup' => array_map(fn($c) => password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT), $codes)]);
    return $codes;
}

/* ---------- „Diesem Gerät 30 Tage vertrauen“ ---------- */
function rs_2fa_trust_key(): string { $t = rs_2fa(); return hash('sha256', ($t['secret'] ?? '') . '|' . ($t['trust_salt'] ?? '') . '|' . rs_config()['admin_hash'] . '|' . rs_secret()); }
function rs_2fa_trust_set(int $days = 30): void {
    $exp = time() + $days * 86400; $v = $exp . '.' . hash_hmac('sha256', (string)$exp, rs_2fa_trust_key());
    setcookie('reserved_trust', $v, ['expires' => $exp, 'path' => rs_base() . '/admin/', 'secure' => rs_https(), 'httponly' => true, 'samesite' => 'Strict']);
}
function rs_2fa_trusted(): bool {
    $v = (string)($_COOKIE['reserved_trust'] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $v, $m) || (int)$m[1] < time()) return false;
    return hash_equals(hash_hmac('sha256', $m[1], rs_2fa_trust_key()), $m[2]);
}

/* ---------- Passwort vergessen ---------- */
function rs_pw_reset_request(): void {
    $c = rs_config();
    $tok = bin2hex(random_bytes(20));
    rs_write('passwort-reset', ['hash' => hash('sha256', $tok), 'exp' => time() + 1800]);
    rs_mail($c['site']['email'], 'Admin-Passwort zurücksetzen', "Hallo,\n\njemand möchte das Passwort für den Admin-Bereich von reserved.social zurücksetzen.\n\nNeues Passwort festlegen (Link gilt 30 Minuten):\n" . rs_abs_url('admin/?reset=' . $tok) . "\n\nWarst du das nicht? Dann ignoriere diese Mail einfach – dein Passwort bleibt, wie es ist.\n");
}
function rs_pw_reset_valid(string $tok): bool {
    $r = rs_read('passwort-reset', []);
    return preg_match('/^[a-f0-9]{40}$/', $tok) && !empty($r['hash']) && (int)$r['exp'] > time() && hash_equals($r['hash'], hash('sha256', $tok));
}
