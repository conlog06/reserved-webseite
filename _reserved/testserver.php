<?php
/* Nur zum Testen auf dem eigenen Rechner:  php -S localhost:8000 _reserved/testserver.php
   Bildet die Regeln der .htaccess für den eingebauten PHP-Server nach. */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/admin') { header('Location: /admin/'); return true; }
if (preg_match('#^/admin/(index\.php)?$#', $path)) { $_SERVER['SCRIPT_NAME'] = '/admin/index.php'; require $root . '/admin/index.php'; return true; }
if ($path === '/_reserved/api.php') { $_SERVER['SCRIPT_NAME'] = '/_reserved/api.php'; require $root . '/_reserved/api.php'; return true; }
if (preg_match('#^/_reserved/#', $path)) { http_response_code(403); echo 'Gesperrt'; return true; }
if ($path === '/angebot') { header('Location: /angebot/'); return true; }
if ($path === '/start') { header('Location: /start/'); return true; }
if ($path === '/plan') { header('Location: /plan/'); return true; }
if ($path === '/plan/danke') { header('Location: /plan/danke/' . (($q = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY)) ? '?' . $q : '')); return true; }
if ($path === '/ueber-mich') { header('Location: /ueber-mich/'); return true; }
if ($path === '/ugc') { header('Location: /ugc/'); return true; }
if ($path === '/' || $path === '/index.html' || $path === '/angebot/' || $path === '/angebot/index.html' || $path === '/start/' || $path === '/start/index.html' || $path === '/ueber-mich/' || $path === '/ueber-mich/index.html' || $path === '/plan/' || $path === '/plan/index.html' || $path === '/plan/danke/' || $path === '/plan/danke/index.html' || $path === '/ugc/' || $path === '/ugc/index.html') { $_SERVER['SCRIPT_NAME'] = '/_reserved/gate.php'; require $root . '/_reserved/gate.php'; return true; }
return false;
