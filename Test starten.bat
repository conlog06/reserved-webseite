@echo off
chcp 65001 >nul
title reserved.social - Testserver
cd /d "%~dp0"
echo.
echo   reserved.social - Testversion
echo   ========================
echo.

set "PHPEXE="
where php >nul 2>nul && set "PHPEXE=php"
if not defined PHPEXE if exist "C:\xampp\php\php.exe" set "PHPEXE=C:\xampp\php\php.exe"
if not defined PHPEXE (
  for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3*") do if exist "%%D\php.exe" set "PHPEXE=%%D\php.exe"
)

if not defined PHPEXE (
  echo   PHP ist auf diesem PC noch nicht installiert.
  echo   Ich installiere es jetzt einmalig ^(kostenlos, offizielle Version ueber winget^).
  echo.
  winget install --id PHP.PHP.8.3 -e --accept-source-agreements --accept-package-agreements
  for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3*") do if exist "%%D\php.exe" set "PHPEXE=%%D\php.exe"
)

if not defined PHPEXE (
  echo.
  echo   PHP konnte nicht gefunden werden. Bitte dieses Fenster schliessen
  echo   und "Test starten.bat" noch einmal doppelklicken.
  pause
  exit /b
)

echo   Webseite:      http://localhost:8123
echo   Admin-Bereich: http://localhost:8123/admin/
echo   Beim ersten Oeffnen des Admins legst du dein Passwort fest
echo   ^(der Einrichtungscode wird beim lokalen Test direkt angezeigt^).
echo.
echo   Zum Beenden dieses Fenster schliessen.
echo.
start "" "http://localhost:8123/"
start "" "http://localhost:8123/admin/"
"%PHPEXE%" -d upload_max_filesize=200M -d post_max_size=210M -S localhost:8123 _reserved/testserver.php
pause
