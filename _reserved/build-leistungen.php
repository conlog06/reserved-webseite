<?php
/* ==========================================================================
   Erzeugt die statischen Leistungs-Unterseiten (für GitHub Pages / ohne PHP)
   und die Leistungs-Zeilen in index.html aus den Standardtexten.
   Aufruf:  php _reserved/build-leistungen.php
   Auf dem Server (IONOS) werden die Seiten live aus dem Admin erzeugt.
   ========================================================================== */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/lib.php';
$root = rs_root();
$c = ['site' => ['services' => rs_defaults()['site']['services']]];
$list = rs_services($c);
$tpl = (string)file_get_contents(__DIR__ . '/leistung.html');
foreach (glob($root . '/leistungen/*/index.html') ?: [] as $f) { @unlink($f); @rmdir(dirname($f)); }
foreach ($list as $i => $x) {
    if (empty($x['p_on'])) continue;
    $dir = $root . '/leistungen/' . $x['slug'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $html = str_replace(['<!--cms:fitlink-->', '<!--/cms:fitlink-->'], '', rs_leistung_fill($tpl, $list, $i));
    file_put_contents($dir . '/index.html', $html);
    echo 'leistungen/' . $x['slug'] . "/\n";
}
$rows = '';
foreach ($list as $i => $x) $rows .= "\n        " . rs_svc_row($x, $i + 1, 'leistungen/' . $x['slug'] . '/');
$idx = (string)file_get_contents($root . '/index.html');
$idx = (string)preg_replace('~<!--cms:services-->.*?<!--/cms:services-->~s', '<!--cms:services-->' . str_replace(['\\', '$'], ['\\\\', '\\$'], $rows) . "\n        <!--/cms:services-->", $idx, 1);
file_put_contents($root . '/index.html', $idx);
echo "index.html: Leistungen aktualisiert\n";
