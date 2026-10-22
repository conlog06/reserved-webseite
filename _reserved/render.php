<?php
/* ==========================================================================
   Reserved. – Inhalte aus dem Admin-Bereich in die index.html einsetzen
   - <!--cms:name--> … <!--/cms:name-->  ganze Blöcke (Pakete, Feedback, Instagram …)
   - data-cms="schlüssel"                einzelne Texte
   Ohne PHP (z. B. GitHub Pages) bleibt einfach der Inhalt der index.html stehen.
   ========================================================================== */

declare(strict_types=1);

function rs_block(string $html, string $name, string $inner): string {
    return (string)(preg_replace_callback('~<!--cms:' . preg_quote($name, '~') . '-->.*?<!--/cms:' . preg_quote($name, '~') . '-->~s',
        fn() => $inner, $html, 1) ?? $html);
}
function rs_slug(string $s): string {
    $s = mb_strtolower(strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: 'x';
}
function rs_check_svg(): string { return '<svg aria-label="inklusive"><use href="#i-check"/></svg>'; }

function rs_render_page(string $html, array $c, bool $admin = false): string {
    $S = $c['site'];
    $handle = $S['instagram'] ?: 'reserved.social';

    $html = (string)preg_replace('~<html\b~i', '<html data-cms-live data-api="' . e(rs_url('_reserved/api.php')) . '"', $html, 1);

    /* Einzelne Texte */
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($S) {
        [$all, $tag, $attrs, $key] = $m;
        if (!isset($S[$key]) || !is_string($S[$key]) || trim($S[$key]) === '') return $all;
        $val = strpos($attrs, 'data-fmt') !== false ? rs_fmt($S[$key]) : e($S[$key]);
        return "<$tag$attrs>$val</$tag>";
    }, $html);
    $html = (string)preg_replace('~(<[a-z]+\b[^>]*\bdata-cms-ig="handle"[^>]*>)[^<]*~i', '${1}' . e($handle), $html);

    /* Links */
    $html = (string)preg_replace_callback('~<a\b([^>]*\bdata-cms-href="(mailto|dm|ig)"[^>]*)>~i', function ($m) use ($S, $handle) {
        $href = ['mailto' => 'mailto:' . $S['email'], 'dm' => 'https://ig.me/m/' . rawurlencode($handle), 'ig' => 'https://www.instagram.com/' . rawurlencode($handle) . '/'][$m[2]];
        return '<a' . preg_replace('/\bhref="[^"]*"/', 'href="' . e($href) . '"', $m[1]) . '>';
    }, $html);

    /* Bild "Über uns" */
    if (!empty($S['img_about'])) $html = (string)preg_replace('~(<img\b[^>]*\bdata-cms-img="about"[^>]*?)\ssrc="[^"]*"~i', '$1 src="' . e(rs_url($S['img_about'])) . '"', $html);

    /* Ankündigungsleiste */
    if (!empty($S['bar_on']) && trim($S['bar_text']) !== '') {
        $bar = '<div class="rs-bar" role="region" aria-label="Ankündigung">' . e($S['bar_text']);
        if (trim($S['bar_link_text']) !== '' && trim($S['bar_link']) !== '') $bar .= ' <a href="' . e($S['bar_link']) . '">' . e($S['bar_link_text']) . ' →</a>';
        $html = rs_block($html, 'bar', $bar . '</div>');
        $html = (string)preg_replace('~<html\b~i', '<html class="has-bar"', $html, 1);
    }

    /* Kennzahlen im Hero */
    $proof = '';
    foreach ($S['proof'] as $p) if (trim($p['v'] ?? '') !== '') $proof .= '<div><strong>' . e($p['v']) . '</strong><span>' . e($p['t']) . '</span></div>';
    if ($proof !== '') $html = rs_block($html, 'proof', $proof);

    /* Pakete */
    $html = rs_block($html, 'plans', rs_render_plans($S));
    $ex = '';
    foreach ($S['extras'] as $x) if (trim($x['t'] ?? '') !== '') $ex .= '<div class="extra"><b>' . e($x['t']) . '</b><span>' . e($x['d']) . '</span>' . (trim($x['p'] ?? '') !== '' ? '<i>' . e($x['p']) . '</i>' : '') . '</div>';
    $html = rs_block($html, 'extras', $ex);

    /* Ergebnisse & Feedback */
    $res = ''; $i = 0;
    foreach ($S['results'] as $r) {
        if (trim($r['v'] ?? '') === '') continue;
        $res .= '<div class="result reveal' . ($i ? ' d' . min(3, $i) : '') . '"><strong>' . e($r['v']) . '</strong><p>' . e($r['t']) . '</p><span>' . e($r['l']) . '</span></div>';
        $i++;
    }
    $html = rs_block($html, 'results', $res);
    $html = rs_block($html, 'quotes', rs_render_quotes($S));

    /* Instagram (Feed + Handy im Hero) */
    $ig = rs_ig_data();
    $html = rs_block($html, 'ig', rs_render_ig($ig, $S));
    if ($ig) {
        $ph = '';
        foreach (array_slice($ig['posts'], 0, 6) as $k => $p) $ph .= '<img' . ($k ? '' : ' class="on"') . ' src="' . e(rs_url($p['img'])) . '" alt="' . e(mb_substr($p['caption'], 0, 90)) . '">';
        $html = rs_block($html, 'phone', $ph);
    }
    if ($handle !== 'reserved.social') $html = str_replace(['instagram.com/reserved.social/', 'ig.me/m/reserved.social'], ['instagram.com/' . e($handle) . '/', 'ig.me/m/' . e($handle)], $html);

    /* Rechtliches */
    $html = rs_block($html, 'impressum', rs_render_impressum($S));
    $html = rs_block($html, 'datenschutz', rs_render_datenschutz($S, $c));
    return $html;
}

function rs_render_plans(array $S): string {
    $P = $S['plans'];
    $o = '<div class="plan-switch" role="tablist" aria-label="Paket auswählen">';
    foreach ($P as $i => $p) $o .= '<button type="button" role="tab" data-plan="' . ($i + 1) . '" aria-selected="' . ($i === 1 ? 'true' : 'false') . '">' . e($p['name']) . '</button>';
    $o .= '</div><div class="table-wrap reveal"><table class="plans" id="plans" data-show="2"><thead><tr><th scope="col">Leistung</th>';
    foreach ($P as $i => $p) {
        $o .= '<th scope="col" class="c' . ($i + 1) . ($i === 1 ? ' col-hl' : '') . '"><span class="tier">' . e($p['tier']) . '</span><span class="pname">' . e($p['name']) . '</span>'
            . (trim($p['price'] ?? '') !== '' ? '<span class="pprice">' . e($p['price']) . '</span>' : '') . '</th>';
    }
    $o .= '</tr></thead><tbody>';
    foreach ($S['plan_rows'] as $r) {
        if (trim($r['l'] ?? '') === '') continue;
        $o .= '<tr><td>' . e($r['l']) . '</td>';
        foreach ([0, 1, 2] as $i) {
            $v = trim((string)($r['v'][$i] ?? ''));
            $cls = 'c' . ($i + 1) . ($i === 1 ? ' col-hl' : '');
            if (in_array($v, ['✓', '✔', 'x', 'X', 'ja', 'Ja'], true)) $o .= '<td class="' . $cls . '">' . rs_check_svg() . '</td>';
            elseif (in_array($v, ['', '—', '-', '–', 'nein', 'Nein'], true)) $o .= '<td class="' . $cls . ' no" aria-label="nicht enthalten">—</td>';
            else $o .= '<td class="' . $cls . '">' . e($v) . '</td>';
        }
        $o .= '</tr>';
    }
    $o .= '</tbody><tfoot><tr><td class="muted plans-note">' . e($S['plans_note']) . '</td>';
    foreach ($P as $i => $p) $o .= '<td class="c' . ($i + 1) . ($i === 1 ? ' col-hl' : '') . '"><a class="btn ' . ($i === 1 ? 'btn-primary' : 'btn-ghost') . '" data-paket="' . e($p['name']) . '" data-track="plan-' . ($i + 1) . '" href="#kontakt">Anfragen</a></td>';
    return $o . '</tr></tfoot></table></div>';
}

function rs_render_quotes(array $S): string {
    $qs = array_values(array_filter($S['quotes'], fn($q) => !empty($q['on']) && trim($q['t'] ?? '') !== ''));
    if (!$qs) return '';
    $companies = [];
    foreach ($qs as $q) if (trim($q['c']) !== '') $companies[rs_slug($q['c'])] = $q['c'];
    $o = '<div class="section-head quotes-head"><h3 class="reveal">Alle Stimmen</h3>';
    if (count($companies) > 1) {
        $o .= '<div class="filters reveal" role="group" aria-label="Feedback filtern"><button type="button" class="chip" data-filter="all" aria-pressed="true">Alle</button>';
        foreach ($companies as $slug => $name) $o .= '<button type="button" class="chip" data-filter="' . e($slug) . '" aria-pressed="false">' . e($name) . '</button>';
        $o .= '</div>';
    }
    $o .= '</div><div class="wall" id="wall">';
    foreach ($qs as $i => $q) {
        $who = trim($q['n'] . ($q['c'] !== '' ? ', ' . $q['c'] : ''), ', ');
        $o .= '<figure class="quote' . (!empty($q['blue']) ? ' blue' : '') . ' reveal' . ($i >= 6 ? ' more' : '') . '" data-client="' . e(rs_slug($q['c'])) . '"><span class="qm">“</span><blockquote>'
            . e($q['t']) . '</blockquote><figcaption>— ' . e($who) . (trim($q['note'] ?? '') !== '' ? '<small>' . e($q['note']) . '</small>' : '') . '</figcaption></figure>';
    }
    $o .= '</div>';
    if (count($qs) > 6) $o .= '<button type="button" class="btn btn-ghost more-btn" id="moreBtn">Alle ' . count($qs) . ' Stimmen anzeigen</button>';
    if ($companies) $o .= '<div class="clients reveal"><em>Vertrauen uns</em><span>' . implode('</span><span>', array_map('e', array_values($companies))) . '</span></div>';
    return $o;
}

function rs_render_ig(?array $ig, array $S): string {
    $handle = $S['instagram'] ?: 'reserved.social';
    if (!$ig) {
        // Kein Zugang eingerichtet: statischen Inhalt der index.html behalten
        $tpl = (string)file_get_contents(rs_root() . '/index.html');
        preg_match('~<!--cms:ig-->(.*?)<!--/cms:ig-->~s', $tpl, $m);
        return str_replace(['"assets/img/', '"../assets/img/'], ['"' . rs_url('assets/img/'), '"' . rs_url('assets/img/')], $m[1] ?? '');
    }
    $P = $ig['profile'];
    $num = fn($n) => $n === null ? '–' : ($n >= 10000 ? number_format($n / 1000, 1, ',', '.') . ' Tsd.' : number_format((int)$n, 0, ',', '.'));
    $pic = $P['pic'] ? '<img src="' . e(rs_url($P['pic'])) . '" alt="">' : '<span>R.</span>';
    $o = '<div class="ig-wrap"><aside class="ig-profile reveal"><div class="ig-head"><span class="ig-pic">' . $pic . '</span><div class="ig-id"><b>' . e($P['username'] ?: $handle) . '</b>'
       . '<span>' . e($P['name'] ?: 'Reserved. | Social Media Agentur') . '</span></div></div><ul class="ig-stats">'
       . '<li><b>' . $num($P['media']) . '</b><span>Beiträge</span></li>'
       . ($P['followers'] !== null ? '<li><b>' . $num($P['followers']) . '</b><span>Follower</span></li>' : '')
       . ($P['follows'] !== null ? '<li><b>' . $num($P['follows']) . '</b><span>Gefolgt</span></li>' : '')
       . '</ul><p class="ig-bio">Social Media-Agentur. Für lokale Unternehmen.<br>Weder Zeit noch Personal für Social Media? – Wir übernehmen.</p>'
       . '<div class="ig-btns"><a class="btn btn-dark" href="https://www.instagram.com/' . e($handle) . '/" target="_blank" rel="noopener" data-track="ig-follow"><svg><use href="#i-insta"/></svg> Folgen</a>'
       . '<a class="btn btn-ghost" href="https://ig.me/m/' . e($handle) . '" target="_blank" rel="noopener" data-track="dm">Nachricht</a></div>'
       . '<p class="ig-sync">Live aus Instagram · aktualisiert ' . e(rs_ago((int)$ig['synced'])) . '</p></aside><div class="ig-grid" id="igGrid">';
    $json = [];
    foreach ($ig['posts'] as $i => $p) {
        $ico = $p['type'] === 'CAROUSEL_ALBUM' ? '<span class="tile-ico"><svg><use href="#i-stack"/></svg></span>' : ($p['type'] === 'VIDEO' ? '<span class="tile-ico"><svg><use href="#i-reel"/></svg></span>' : '');
        $hover = '';
        if ($p['likes'] !== null) $hover .= '<span><svg><use href="#i-heart"/></svg>' . $num($p['likes']) . '</span>';
        if ($p['comments'] !== null) $hover .= '<span><svg><use href="#i-comment"/></svg>' . $num($p['comments']) . '</span>';
        $alt = trim(mb_substr((string)preg_replace('/\s+/', ' ', $p['caption']), 0, 120)) ?: 'Instagram-Beitrag';
        $o .= '<button type="button" class="tile reveal' . ($i % 3 ? ' d' . ($i % 3) : '') . '" data-i="' . $i . '"><img src="' . e(rs_url($p['img'])) . '" alt="' . e($alt) . '" loading="lazy" decoding="async">'
            . $ico . ($hover ? '<span class="tile-hover">' . $hover . '</span>' : '') . '</button>';
        $slides = array_map(fn($ch) => ['img' => rs_url($ch['img']), 'video' => $ch['video']], $p['children']);
        $json[] = ['img' => rs_url($p['img']), 'type' => $p['type'], 'video' => $p['video'], 'caption' => $p['caption'], 'permalink' => $p['permalink'],
                   'ts' => $p['ts'], 'likes' => $p['likes'], 'comments' => $p['comments'], 'slides' => $slides];
    }
    $o .= '</div></div>';
    $o .= '<div class="ig-more"><a class="link-arrow" href="https://www.instagram.com/' . e($handle) . '/" target="_blank" rel="noopener" data-track="ig-follow">Alle Beiträge auf Instagram →</a></div>';
    $data = json_encode(['profile' => ['username' => $P['username'] ?: $handle, 'pic' => $P['pic'] ? rs_url($P['pic']) : ''], 'posts' => $json], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return $o . '<script type="application/json" id="igData">' . $data . '</script>';
}

function rs_render_impressum(array $S): string {
    $addr = nl2br(e(trim($S['imp_address'])), false);
    $o = '<h5>Name</h5><p>' . e($S['imp_name']) . ($S['imp_business'] !== '' ? '<br>' . e($S['imp_business']) : '') . '</p>';
    $o .= '<h5>Anschrift</h5><p>' . $addr . '</p>';
    $o .= '<h5>Kontakt</h5><p>E-Mail: <a href="mailto:' . e($S['email']) . '">' . e($S['email']) . '</a></p>';
    if (trim($S['imp_ustid']) !== '') $o .= '<h5>Umsatzsteuer-IdNr. gemäß § 27a UStG</h5><p>' . e($S['imp_ustid']) . '</p>';
    $o .= '<h5>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h5><p>' . e($S['imp_name']) . '<br>' . e(str_replace("\n", ', ', trim($S['imp_address']))) . '</p>';
    return $o;
}

/** Eigener Text aus dem Admin (Absätze, "## Überschrift") oder die Vorlage */
function rs_render_datenschutz(array $S, array $c): string {
    $own = trim((string)$S['datenschutz']);
    if ($own !== '') {
        $o = '';
        foreach (preg_split('/\n\s*\n/', str_replace("\r", '', $own)) as $para) {
            $para = trim($para);
            if (str_starts_with($para, '## ')) $o .= '<h4>' . e(substr($para, 3)) . '</h4>';
            else $o .= '<p>' . rs_fmt($para) . '</p>';
        }
        return $o;
    }
    $v = e($S['imp_name']) . ', ' . e(str_replace("\n", ', ', trim($S['imp_address']))) . ', E-Mail: ' . e($S['email']);
    $months = (int)$c['leads']['keep_months'];
    return <<<HTML
<h4>1. Verantwortliche</h4><p>$v</p>
<h4>2. Aufruf der Webseite</h4><p>Beim Aufruf der Webseite verarbeitet unser Hosting-Anbieter technisch notwendige Daten (z.&nbsp;B. IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browser) in Server-Logfiles, um die Seite sicher und stabil auszuliefern (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;f DSGVO). Schriften und Bilder – auch die Bilder aus unserem Instagram-Profil – werden von unserem eigenen Server geladen; es werden keine Daten an Google, Meta oder andere Dritte übertragen.</p>
<h4>3. Besucherstatistik ohne Cookies</h4><p>Wir zählen Seitenaufrufe anonym: welche Bereiche angesehen werden, Klicks auf Buttons, Herkunft (z.&nbsp;B. „Instagram“ oder „Google“), Gerätetyp und Verweildauer. Es werden keine Cookies gesetzt und keine IP-Adressen gespeichert. Um Besucher eines Tages nicht doppelt zu zählen, wird ein Prüfwert aus IP-Adresse und Browserkennung mit einem täglich wechselnden Zufallswert gebildet, der nach Ablauf des Tages gelöscht wird – eine Wiedererkennung ist nicht möglich. Wenn dein Browser „Do Not Track“ oder „Global Privacy Control“ sendet, zählen wir nichts. Rechtsgrundlage ist unser berechtigtes Interesse an einer datensparsamen Reichweitenmessung (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;f DSGVO, § 25 Abs.&nbsp;2 TDDDG).</p>
<h4>4. Anfrageformular und E-Mail</h4><p>Wenn du uns über das Formular oder per E-Mail kontaktierst, verarbeiten wir deine Angaben (Name, Kontaktdaten, Angaben zu deinem Unternehmen und deine Nachricht), um deine Anfrage zu beantworten und ein Angebot zu erstellen (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;b DSGVO). Du erhältst eine automatische Bestätigung per E-Mail. Anfragen werden spätestens nach $months Monaten gelöscht, sofern daraus kein Auftrag entsteht und keine gesetzlichen Aufbewahrungspflichten bestehen.</p>
<h4>5. Instagram</h4><p>Unsere Webseite zeigt Beiträge aus unserem Instagram-Profil. Die Bilder liegen auf unserem Server. Erst wenn du einen Link zu Instagram anklickst oder ein Video in der Großansicht abspielst, wird eine Verbindung zu Meta Platforms Ireland Ltd. hergestellt; dann gelten deren Datenschutzbestimmungen.</p>
<h4>6. Deine Rechte</h4><p>Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit sowie Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen. Außerdem kannst du dich bei einer Datenschutz-Aufsichtsbehörde beschweren. Schreib uns dafür einfach eine E-Mail.</p>
HTML;
}


/* ==========================================================================
   Landingpage /angebot/
   ========================================================================== */
function rs_render_angebot(string $html, array $c): string {
    $A = $c['angebot']; $S = $c['site'];
    $html = rs_render_page($html, $c);
    /* Texte */
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms-lp="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($A) {
        $v = $A[$m[3]] ?? null;
        return is_string($v) && trim($v) !== '' ? '<' . $m[1] . $m[2] . '>' . e($v) . '</' . $m[1] . '>' : $m[0];
    }, $html);
    $check = '<svg aria-hidden="true"><use href="#i-check"/></svg>';
    $html = rs_block($html, 'lp_bullets', implode('', array_map(fn($b) => '<li>' . $check . '<span>' . e($b) . '</span></li>', array_filter($A['bullets'], fn($b) => trim($b) !== ''))));

    /* Bilderstapel: neueste Instagram-Bilder, sonst die festen Slides */
    if ($ig = rs_ig_data()) {
        $imgs = array_slice($ig['posts'], 0, 3);
        if (count($imgs) === 3) $html = rs_block($html, 'lp_stack', implode('', array_map(fn($p) => '<img src="' . e(rs_url($p['img'])) . '" alt="">', array_reverse($imgs))));
    }

    /* Angebot mit Countdown */
    $offer = '';
    if (!empty($A['offer_on']) && trim($A['offer_title']) !== '') {
        $until = rs_ts($A['offer_until']);
        $offer = '<div class="offer">' . (trim($A['offer_badge']) !== '' ? '<span class="offer-badge">✦ ' . e($A['offer_badge']) . '</span>' : '')
               . '<h3>' . e($A['offer_title']) . '</h3>' . (trim($A['offer_text']) !== '' ? '<p>' . rs_fmt($A['offer_text']) . '</p>' : '');
        if ($until && $until > time()) {
            $offer .= '<div class="offer-count" data-until="' . ($until * 1000) . '" role="timer" aria-label="Zeit bis Aktionsende">';
            foreach (['d' => 'Tage', 'h' => 'Std.', 'm' => 'Min.', 's' => 'Sek.'] as $k => $l) $offer .= '<div><b data-u="' . $k . '">00</b><small>' . $l . '</small></div>';
            $offer .= '</div><small class="offer-end">Gültig bis ' . e(rs_date_de($until, true)) . '</small>';
        }
        $offer .= '</div>';
    }
    $html = rs_block($html, 'lp_offer', $offer);

    /* Pakete als Karten */
    $cards = '';
    foreach ($S['plans'] as $i => $p) {
        $feat = [];
        foreach ($S['plan_rows'] as $r) {
            $v = trim((string)($r['v'][$i] ?? ''));
            if (in_array($v, ['', '—', '-', '–', 'nein', 'Nein'], true)) continue;
            if (in_array($v, ['✓', '✔', 'x', 'X', 'ja', 'Ja'], true)) $feat[] = $r['l'];
            elseif (preg_match('/^(\d+|bis zu \d+)$/iu', $v)) $feat[] = $v . ' ' . $r['l'];
            else $feat[] = $r['l'] . ': ' . $v;
            if (count($feat) >= 7) break;
        }
        $cards .= '<article class="pcard' . ($i === 1 ? ' hl' : '') . ' reveal' . ($i ? ' d' . $i : '') . '"><span class="tier">' . e($p['tier']) . '</span><h3>' . e($p['name']) . '</h3>'
                . (trim($p['price'] ?? '') !== '' ? '<span class="pprice">' . e($p['price']) . '</span>' : '')
                . '<ul>' . implode('', array_map(fn($f) => '<li>' . $check . e($f) . '</li>', $feat)) . '</ul>'
                . '<a class="btn ' . ($i === 1 ? 'btn-primary' : 'btn-ghost') . '" href="#kontakt" data-paket="' . e($p['name']) . '" data-track="plan-' . ($i + 1) . '">' . e($p['name']) . ' anfragen</a></article>';
    }
    $html = (string)preg_replace('~<div class="pcards">.*?</div>\s*</div>\s*</section>~s', '<div class="pcards">' . str_replace(['\\', '$'], ['\\\\', '\$'], $cards) . '</div></div></section>', $html, 1);
    $html = str_replace('Preise individuell – wir melden uns innerhalb kurzer Zeit.', e($S['plans_note']), $html);

    /* Stimmen */
    $qs = array_slice(array_values(array_filter($S['quotes'], fn($q) => !empty($q['on']))), 0, max(1, (int)$A['quotes_n']));
    $qh = '';
    foreach ($qs as $i => $q) {
        $who = trim($q['n'] . ($q['c'] !== '' ? ', ' . $q['c'] : ''), ', ');
        $qh .= '<figure class="quote' . (!empty($q['blue']) ? ' blue' : '') . ' reveal' . ($i % 3 ? ' d' . ($i % 3) : '') . '"><span class="qm">“</span><blockquote>' . e($q['t']) . '</blockquote><figcaption>— ' . e($who)
             . (trim($q['note'] ?? '') !== '' ? '<small>' . e($q['note']) . '</small>' : '') . '</figcaption></figure>';
    }
    $html = (string)preg_replace('~<div class="lp-quotes">.*?</div>\s*</div>\s*</section>~s', '<div class="lp-quotes">' . str_replace(['\\', '$'], ['\\\\', '\$'], $qh) . '</div></div></section>', $html, 1);

    /* FAQ */
    $faq = '';
    foreach ($A['faq'] as $f) if (trim($f['q'] ?? '') !== '') $faq .= '<details class="reveal"><summary>' . e($f['q']) . '</summary><p>' . rs_fmt((string)$f['a']) . '</p></details>';
    $html = (string)preg_replace('~<div class="faq">.*?</div>\s*</div>\s*</section>~s', '<div class="faq">' . str_replace(['\\', '$'], ['\\\\', '\$'], $faq) . '</div></div></section>', $html, 1);
    if ($faq === '') $A['show']['faq'] = false;

    /* Abschnitte ein-/ausblenden */
    foreach (['results', 'steps', 'ig', 'plans', 'quotes', 'faq'] as $k) {
        if (empty($A['show'][$k])) $html = rs_block($html, 'lp_' . $k, '');
    }
    return $html;
}
