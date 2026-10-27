<?php
/* ==========================================================================
   reserved.social – Inhalte aus dem Admin-Bereich in die index.html einsetzen
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
function rs_check_svg(): string { return '<svg role="img" aria-label="inklusive"><use href="#i-check"/></svg>'; }

/** CSS direkt in die Seite schreiben: keine blockierende Anfrage beim ersten Aufbau */
function rs_inline_css(string $html): string {
    static $css = null;
    if ($css === null) {
        $css = (string)@file_get_contents(rs_root() . '/assets/css/style.css');
        $css = str_replace('url(../fonts/', 'url(' . rs_url('assets/fonts/'), $css);
        $css = (string)preg_replace(['~/\*.*?\*/~s', '~\s*\n\s*~'], ['', "\n"], $css);
    }
    if ($css === '' || strlen($css) > 120000) return $html;
    return (string)preg_replace('~<link rel="stylesheet" href="(\.\./)?assets/css/style\.css">~', '<style>' . str_replace(['\\', '$'], ['\\\\', '\\$'], $css) . '</style>', $html, 1);
}

function rs_render_page(string $html, array $c, bool $admin = false): string {
    $S = $c['site'];
    $handle = $S['instagram'] ?: 'reserved.social';

    $html = (string)preg_replace('~<html\b~i', '<html data-cms-live data-api="' . e(rs_url('_reserved/api.php')) . '"', $html, 1);
    if (empty($c['ugc']['on'])) for ($i = 0; $i < 4 && str_contains($html, '<!--cms:ugclink-->'); $i++) $html = rs_block($html, 'ugclink', '');

    /* Einzelne Texte */
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($S) {
        [$all, $tag, $attrs, $key] = $m;
        if (!isset($S[$key]) || !is_string($S[$key]) || trim($S[$key]) === '') return $all;
        $val = strpos($attrs, 'data-fmt') !== false ? rs_fmt($S[$key]) : e($S[$key]);
        return "<$tag$attrs>$val</$tag>";
    }, $html);
    $html = (string)preg_replace('~(<[a-z]+\b[^>]*\bdata-cms-ig="handle"[^>]*>)[^<]*~i', '${1}' . e($handle), $html);

    /* Links */
    $wa = preg_replace('/\D+/', '', (string)($S['whatsapp'] ?? ''));
    if ($wa !== '' && $wa[0] === '0') $wa = '49' . substr($wa, 1);
    if ($wa === '') $html = (string)preg_replace('~\s*<a class="way"[^>]*data-cms-href="wa".*?</a>~s', '', $html);
    $html = (string)preg_replace_callback('~<a\b([^>]*\bdata-cms-href="(mailto|dm|ig|wa)"[^>]*)>~i', function ($m) use ($S, $handle, $wa) {
        $href = ['mailto' => 'mailto:' . $S['email'], 'dm' => 'https://ig.me/m/' . rawurlencode($handle), 'ig' => 'https://www.instagram.com/' . rawurlencode($handle) . '/', 'wa' => 'https://wa.me/' . $wa][$m[2]];
        return '<a' . preg_replace('/\bhref="[^"]*"/', 'href="' . e($href) . '"', $m[1]) . '>';
    }, $html);

    /* Bild "Über uns" */
    if (!empty($S['img_about'])) {
        $html = (string)preg_replace_callback('~<img\b[^>]*\bdata-cms-img="about"[^>]*>~i', fn($m) => (string)preg_replace(['~\s(srcset|sizes)="[^"]*"~', '~\ssrc="[^"]*"~'], ['', ' src="' . e(rs_url($S['img_about'])) . '"'], $m[0]), $html);
    }

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

    /* Leistungen als Zeilen */
    $sv = ''; $n = 0;
    foreach ((array)($S['services'] ?? []) as $x) if (trim($x['t'] ?? '') !== '') { $n++;
        $sv .= '<div class="svc-row reveal"><span class="svc-n">' . sprintf('%02d', $n) . '</span><h3>' . e($x['t']) . (trim($x['tag'] ?? '') !== '' ? ' <small>' . e($x['tag']) . '</small>' : '') . '</h3><p>' . e($x['d'] ?? '') . '</p></div>'; }
    if ($sv !== '') $html = rs_block($html, 'services', $sv);

    /* Reel-Statistiken (nur mit echten Einträgen) */
    $html = rs_block($html, 'cases', rs_render_cases((array)($S['cases'] ?? [])));

    /* Über mich: Kennzahlen */
    $st = '';
    foreach ((array)($S['about_stats'] ?? []) as $x) if (trim($x['v'] ?? '') !== '') $st .= '<div><b>' . e($x['v']) . '</b><span>' . e($x['t']) . '</span></div>';
    if ($st !== '') $html = rs_block($html, 'aboutstats', $st);

    /* Häufige Fragen */
    $faq = '';
    foreach ((array)($S['faq'] ?? []) as $f) if (trim($f['q'] ?? '') !== '') $faq .= '<details><summary>' . e($f['q']) . '</summary><p>' . rs_fmt((string)$f['a']) . '</p></details>';
    if ($faq !== '') $html = rs_block($html, 'homefaq', $faq);
    else $html = (string)preg_replace('~\s*<!-- =+ FAQ =+ -->.*?</section>~s', '', $html, 1);

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
        foreach (array_slice($ig['posts'], 0, 6) as $k => $p) {
            $set = rs_ig_srcset($p['img']); $alt = ' alt="' . e(mb_substr($p['caption'], 0, 90)) . '" sizes="(max-width: 720px) 74vw, 330px" width="1080" height="1350"';
            $ph .= $k ? '<img data-src="' . e(rs_url($p['img'])) . '"' . ($set ? ' data-' . ltrim($set) : '') . $alt . '>'
                      : '<img class="on" src="' . e(rs_url($p['img'])) . '"' . $set . $alt . ' fetchpriority="high">';
        }
        $html = rs_block($html, 'phone', $ph);
        $html = rs_block($html, 'heroposts', rs_render_heroposts($ig));
    }
    if ($handle !== 'reserved.social') $html = str_replace(['instagram.com/reserved.social/', 'ig.me/m/reserved.social'], ['instagram.com/' . e($handle) . '/', 'ig.me/m/' . e($handle)], $html);

    /* Fit-Check ausgeschaltet → Hinweise entfernen */
    if (empty($c['check']['on'])) { $html = rs_block($html, 'fclink', ''); $html = rs_block($html, 'fcfoot', ''); }

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
       . '<span>' . e($P['name'] ?: 'reserved.social | Social Media Agentur') . '</span></div></div><ul class="ig-stats">'
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
        $o .= '<button type="button" class="tile reveal' . ($i % 3 ? ' d' . ($i % 3) : '') . '" data-i="' . $i . '"><img src="' . e(rs_url($p['img'])) . '"' . rs_ig_srcset($p['img']) . ' sizes="(max-width: 720px) 33vw, (max-width: 1020px) 30vw, 270px" alt="' . e($alt) . '" loading="lazy" decoding="async" width="1080" height="1350">'
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

/** Vier neueste Beiträge als Kacheln unter dem Startbereich (öffnen die Großansicht) */
function rs_render_heroposts(array $ig): string {
    $types = ['VIDEO' => ['Reel', 'i-reel'], 'CAROUSEL_ALBUM' => ['Karussell', 'i-stack'], 'IMAGE' => ['Beitrag', '']];
    $o = '';
    foreach (array_slice($ig['posts'], 0, 4) as $i => $p) {
        [$k, $ico] = $types[$p['type']] ?? ['Beitrag', ''];
        if (!empty($p['ts'])) $k .= ' · ' . date('j.', (int)$p['ts']) . ' ' . ['', 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'][(int)date('n', (int)$p['ts'])];
        $cap = trim((string)preg_replace('/\s+/', ' ', (string)preg_replace('/[#@][\wÄÖÜäöüß.]+/u', '', $p['caption'])));
        $cap = mb_strlen($cap) > 90 ? rtrim(mb_substr($cap, 0, 88)) . ' …' : ($cap ?: 'Beitrag auf Instagram');
        $o .= '<button type="button" class="ptile reveal' . ($i ? ' d' . $i : '') . '" data-ig-open="' . $i . '"><span class="ptile-media"><img src="' . e(rs_url($p['img'])) . '"' . rs_ig_srcset($p['img'])
            . ' sizes="(max-width: 720px) 46vw, 290px" alt="" loading="lazy" decoding="async" width="1080" height="1350">'
            . ($ico ? '<span class="tile-ico"><svg><use href="#' . $ico . '"/></svg></span>' : '') . '</span><span class="ptile-meta"><span class="ptile-k">' . e($k) . '</span><span class="ptile-h">' . e($cap) . '</span></span></button>';
    }
    return $o;
}

function rs_render_cases(array $cases): string {
    $cases = array_values(array_filter($cases, fn($x) => trim($x['firma'] ?? '') !== ''));
    if (!$cases) return '';
    $o = '<section class="section cases" id="reels" data-sec="reels"><div class="container"><div class="section-head"><div><span class="eyebrow reveal">Reels</span>'
       . '<h2 class="reveal d1">Ergebnisse in <span class="script">Zahlen.</span></h2></div><p class="lead reveal d2">Echte Zahlen aus Reels, die wir für Unternehmen aus der Region gedreht haben.</p></div><div class="case-grid">';
    foreach ($cases as $i => $x) {
        $kpi = '';
        foreach (['views' => 'Aufrufe', 'likes' => 'Likes', 'shares' => 'Geteilt'] as $k => $l) if (trim($x[$k] ?? '') !== '') $kpi .= '<div><strong>' . e($x[$k]) . '</strong><span>' . $l . '</span></div>';
        $media = !empty($x['img']) ? '<img src="' . e(rs_url($x['img'])) . '" alt="" loading="lazy" decoding="async">' : '<span class="case-ph">' . e(mb_substr($x['firma'], 0, 1)) . '</span>';
        $tag = !empty($x['link']) ? 'a href="' . e($x['link']) . '" target="_blank" rel="noopener" data-track="reel-case"' : 'div';
        $o .= '<article class="case reveal' . ($i % 3 ? ' d' . ($i % 3) : '') . '"><' . $tag . ' class="case-media">' . $media . (trim($x['branche'] ?? '') !== '' ? '<span class="case-tag">' . e($x['branche']) . '</span>' : '')
            . (!empty($x['link']) ? '<span class="case-play" aria-hidden="true"></span>' : '') . '</' . explode(' ', $tag)[0] . '>'
            . '<h3>' . e($x['firma']) . '</h3>' . (trim($x['text'] ?? '') !== '' ? '<p>' . e($x['text']) . '</p>' : '') . ($kpi ? '<div class="case-kpis">' . $kpi . '</div>' : '') . '</article>';
    }
    return $o . '</div></div></section>';
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
<h4>5. Kostenloser Download und E-Mails</h4><p>Wenn du dir unseren kostenlosen Content-Plan Starter holst, speichern wir deinen Vornamen, deine E-Mail-Adresse, den Zeitpunkt der Anmeldung und der Bestätigung. Du bekommst zuerst eine E-Mail mit einem Bestätigungslink (Double-Opt-in); erst danach schicken wir dir den Plan und gelegentlich Social-Media-Tipps per E-Mail. Rechtsgrundlage ist deine Einwilligung (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;a DSGVO), die du jederzeit über den Abmeldelink in jeder E-Mail oder per Nachricht an uns widerrufen kannst. Nutzen wir für den Versand einen Newsletter-Dienst, verarbeitet dieser die Daten in unserem Auftrag (Auftragsverarbeitung nach Art.&nbsp;28 DSGVO). Nach der Abmeldung löschen wir deine Daten, sofern wir sie nicht als Nachweis der früheren Einwilligung aufbewahren müssen.</p>
<h4>6. Terminbuchung</h4><p>Für Terminbuchungen verlinken wir auf einen externen Kalender-Dienst. Erst wenn du den Link anklickst, wird eine Verbindung zu diesem Anbieter hergestellt; dort gelten dessen Datenschutzbestimmungen.</p>
<h4>7. Instagram</h4><p>Unsere Webseite zeigt Beiträge aus unserem Instagram-Profil. Die Bilder liegen auf unserem Server. Erst wenn du einen Link zu Instagram anklickst oder ein Video in der Großansicht abspielst, wird eine Verbindung zu Meta Platforms Ireland Ltd. hergestellt; dann gelten deren Datenschutzbestimmungen.</p>
<h4>8. Deine Rechte</h4><p>Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit sowie Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen. Außerdem kannst du dich bei einer Datenschutz-Aufsichtsbehörde beschweren. Schreib uns dafür einfach eine E-Mail.</p>
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


/* ==========================================================================
   Funnel /plan/ und /plan/danke/
   ========================================================================== */
function rs_render_plan(string $html, array $c, bool $danke): string {
    $F = $c['funnel'];
    $html = rs_render_page($html, $c);
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms-pl="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($F) {
        $v = $F[$m[3]] ?? null;
        return is_string($v) && trim($v) !== '' ? '<' . $m[1] . $m[2] . '>' . e($v) . '</' . $m[1] . '>' : $m[0];
    }, $html);
    $li = fn(array $l) => implode('', array_map(fn($b) => '<li><svg><use href="#i-check"/></svg><span>' . e($b) . '</span></li>', array_filter($l, fn($b) => trim((string)$b) !== '')));
    $html = rs_block($html, 'plbullets', $li((array)$F['bullets']));
    if (!$danke) return $html;

    $html = rs_block($html, 'ploffer', $li((array)$F['offer_bullets']));
    $price = trim((string)$F['price']);
    $html = rs_block($html, 'plprice', $price !== '' ? '<span class="price-k">Strategie-Begleitung mit Victoria</span><b class="price">' . e($price) . '</b>' : '');
    if (trim((string)$F['booking_url']) !== '') $html = (string)preg_replace('~id="bookBtn" href="[^"]*"~', 'id="bookBtn" href="' . e($F['booking_url']) . '" target="_blank" rel="noopener"', $html, 1);
    $s = rs_funnel_find((string)($_GET['t'] ?? ''));
    if ($s && $s['confirmed'] && !$s['unsub']) {
        $html = (string)preg_replace('~id="planDl" href="[^"]*"~', 'id="planDl" href="' . e(rs_funnel_links($s)['pdf']) . '"', $html, 1);
        $first = explode(' ', $s['name'])[0];
        $html = (string)preg_replace_callback('~(<h1 class="reveal d1" data-cms-pl="danke_title">)(.*?)(</h1>)~s', fn($m) => $m[1] . (preg_match('/[!.]$/u', $m[2]) ? rtrim($m[2], '!.') . ', ' . e($first) . '!' : $m[2]) . $m[3], $html, 1);
    } else {
        $html = rs_block($html, 'pldl', '<span class="done-ic"><svg><use href="#i-mail"/></svg></span><h1>Fast geschafft!</h1><p class="lead">Bitte bestätige zuerst deine E-Mail-Adresse über den Link in unserer Mail – dann kannst du den Plan hier herunterladen.</p><div class="hero-actions"><a class="btn btn-primary" href="' . e(rs_url('plan/')) . '">Plan anfordern</a></div>');
    }
    return $html;
}

/* ==========================================================================
   Über mich /ueber-mich/
   ========================================================================== */
function rs_render_about(string $html, array $c): string {
    $html = rs_render_page($html, $c);
    $v = '';
    $items = array_values(array_filter((array)($c['site']['vita'] ?? []), fn($x) => trim($x['t'] ?? '') !== ''));
    foreach ($items as $i => $x) $v .= '<li class="reveal' . ($i === count($items) - 1 ? ' now' : '') . '"><span class="vita-k">' . e($x['k'] ?? '') . '</span><div><h3>' . e($x['t']) . '</h3><p>' . rs_fmt((string)($x['d'] ?? '')) . '</p></div></li>';
    if ($v !== '') $html = rs_block($html, 'vita', $v);
    return $html;
}

/* ==========================================================================
   Fit-Check /start/
   ========================================================================== */
function rs_render_check(string $html, array $c): string {
    $K = $c['check']; $S = $c['site'];
    $html = rs_render_page($html, $c);
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms-fc="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($K) {
        $v = $K[$m[3]] ?? null;
        return is_string($v) && trim($v) !== '' ? '<' . $m[1] . $m[2] . '>' . e($v) . '</' . $m[1] . '>' : $m[0];
    }, $html);
    $bud = array_values(array_filter(array_map('trim', (array)($K['budget'] ?? [])), fn($b) => $b !== ''));
    if (empty($K['budget_on']) || !$bud) $html = rs_block($html, 'fcbudget', '');
    else {
        $o = '';
        foreach ($bud as $i => $b) $o .= '<label class="fc-opt"><input type="radio" name="budget" value="' . e($b) . '"><span><kbd>' . chr(65 + min($i, 25)) . '</kbd><b>' . e($b) . '</b></span></label>';
        $html = (string)preg_replace_callback('~(<!--cms:fcbudget-->.*?<div class="fc-opts" data-auto>).*?(</div></section><!--/cms:fcbudget-->)~s', fn($m) => $m[1] . $o . $m[2], $html, 1);
    }
    if (!empty($S['img_avatar'])) $html = (string)preg_replace('~(<img\b[^>]*\bdata-cms-img="avatar"[^>]*?)\ssrc="[^"]*"~i', '$1 src="' . e(rs_url($S['img_avatar'])) . '"', $html);
    return $html;
}

/* ==========================================================================
   UGC-Seite /ugc/ (Victorias Creator Kit)
   ========================================================================== */
/** „a | b | c“-Zeilen in Arrays zerlegen (leere Zeilen fallen weg) */
function rs_lines(string $s, int $parts = 2): array {
    $out = [];
    foreach (preg_split('/\R/', $s) ?: [] as $l) {
        if (trim($l) === '') continue;
        $p = array_map('trim', explode('|', $l, $parts));
        $out[] = array_pad($p, $parts, '');
    }
    return $out;
}
function rs_ugc_blocks(array $U): array {
    $B = [];
    $B['ugstats'] = implode('', array_map(fn($x) => '<div class="ug-stat reveal"><b>' . e($x[0]) . '</b><span>' . e($x[1]) . '</span></div>', rs_lines((string)$U['stats'])));
    $card = function (string $name, string $head, string $ages, string $facts) {
        $rows = rs_lines($ages); $f = rs_lines($facts);
        if ($head === '' && !$rows && !$f) return '';
        $max = 0.0; foreach ($rows as $r) $max = max($max, (float)str_replace(',', '.', (string)preg_replace('/[^\d,.]/', '', $r[1])));
        $o = '<article class="ug-aud reveal"><h3>' . e($name) . '</h3>' . ($head !== '' ? '<p class="ug-aud-h">' . e($head) . '</p>' : '');
        if ($rows) {
            $o .= '<ul class="ug-bars">';
            foreach ($rows as $r) { $v = (float)str_replace(',', '.', (string)preg_replace('/[^\d,.]/', '', $r[1])); $w = $max > 0 ? round($v / $max * 100) : 0;
                $o .= '<li><span>' . e($r[0]) . '</span><i><s style="width:' . $w . '%"></s></i><b>' . e($r[1]) . '</b></li>'; }
            $o .= '</ul>';
        }
        if ($f) { $o .= '<dl class="ug-facts">'; foreach ($f as $x) $o .= '<div><dt>' . e($x[0]) . '</dt><dd>' . e($x[1]) . '</dd></div>'; $o .= '</dl>'; }
        return $o . '</article>';
    };
    $B['ugaud'] = $card('Instagram', (string)$U['ig_head'], (string)$U['ig_ages'], (string)$U['ig_facts']) . $card('TikTok', (string)$U['tt_head'], (string)$U['tt_ages'], (string)$U['tt_facts']);
    $price = trim((string)$U['price_label']);
    $B['ugsvc'] = implode('', array_map(function ($x) use ($price) {
        $pts = array_filter(array_map('trim', explode(';', $x[2])), fn($p) => $p !== '');
        return '<div class="ug-svc reveal"><div class="ug-svc-h"><h3>' . e($x[0]) . '</h3>' . ($x[1] !== '' ? '<p>' . e($x[1]) . '</p>' : '') . '</div>'
             . '<ul>' . implode('', array_map(fn($p) => '<li>' . e($p) . '</li>', $pts)) . '</ul>' . ($price !== '' ? '<span class="ug-price script">' . e($price) . '</span>' : '') . '</div>';
    }, rs_lines((string)$U['services'], 3)));
    $i = 0;
    $B['ugsteps'] = implode('', array_map(function ($x) use (&$i) { $i++; return '<li class="reveal"><span class="ug-n">' . $i . '</span><h3>' . e($x[0]) . '</h3><p>' . e($x[1]) . '</p></li>'; }, rs_lines((string)$U['steps'])));
    $B['ugbrands'] = implode('', array_map(function ($x) {
        $names = array_filter(array_map('trim', explode(',', $x[1])), fn($n) => $n !== '');
        return '<div class="ug-brand reveal"><h3>' . e($x[0]) . '</h3><ul>' . implode('', array_map(fn($n) => '<li>' . e($n) . '</li>', $names)) . '</ul></div>';
    }, rs_lines((string)$U['brands'])));
    $v = '';
    foreach ((array)($U['videos'] ?? []) as $x) {
        if (empty($x['file'])) continue;
        $v .= '<figure class="ug-vid reveal"><video src="' . e(rs_url($x['file'])) . '"' . (!empty($x['poster']) ? ' poster="' . e(rs_url($x['poster'])) . '"' : '') . ' preload="' . (!empty($x['poster']) ? 'none' : 'metadata') . '" playsinline></video>'
            . '<button type="button" class="ug-play" aria-label="Video abspielen' . (!empty($x['title']) ? ': ' . e($x['title']) : '') . '"><svg><use href="#i-play"/></svg></button>'
            . (!empty($x['title']) ? '<figcaption>' . e($x['title']) . '</figcaption>' : '') . '</figure>';
    }
    $B['ugvideos'] = $v === '' ? '' : '<section class="section ug-videos" data-sec="videos"><div class="container"><div class="section-head"><div><span class="eyebrow reveal">Content</span><h2 class="reveal d1">Beispiel<span class="script">videos.</span></h2></div></div><div class="ug-vids">' . $v . '</div></div></section>';
    return $B;
}
function rs_render_ugc(string $html, array $c): string {
    $U = $c['ugc'];
    $html = rs_render_page($html, $c);
    $html = (string)preg_replace_callback('~<([a-z0-9]+)(\b[^>]*?\bdata-cms-ug="([a-z_]+)"[^>]*)>(.*?)</\1>~is', function ($m) use ($U) {
        $v = $U[$m[3]] ?? null;
        return is_string($v) && trim($v) !== '' ? '<' . $m[1] . $m[2] . '>' . e($v) . '</' . $m[1] . '>' : $m[0];
    }, $html);
    $hrefs = ['mail' => $U['email'] !== '' ? 'mailto:' . $U['email'] : '', 'ig' => $U['ig'] !== '' ? 'https://www.instagram.com/' . rawurlencode($U['ig']) . '/' : '',
              'tt' => $U['tt'] !== '' ? 'https://www.tiktok.com/@' . rawurlencode($U['tt']) : '', 'portfolio' => preg_match('~^https://~i', (string)$U['portfolio']) ? $U['portfolio'] : ''];
    $html = (string)preg_replace_callback('~<a\b([^>]*?)\bhref="[^"]*"([^>]*?\bdata-ug-href="([a-z]+)"[^>]*)>~i', function ($m) use ($hrefs) {
        $h = $hrefs[$m[3]] ?? '';
        return '<a' . $m[1] . 'href="' . e($h) . '"' . $m[2] . ($h === '' ? ' hidden' : '') . '>';
    }, $html);
    foreach (rs_ugc_blocks($U) as $k => $v) $html = rs_block($html, $k, $v);
    if (!empty($c['site']['img_ugc'])) $html = (string)preg_replace('~(<img\b[^>]*\bdata-cms-img="ugc"[^>]*?)\ssrc="[^"]*"~i', '$1 src="' . e(rs_url($c['site']['img_ugc'])) . '"', $html);
    return $html;
}
