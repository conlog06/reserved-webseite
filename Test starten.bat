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

rem Bildbearbeitung (GD) einschalten, falls PHP sie nicht schon laedt
set "PHPOPT="
"%PHPEXE%" -m 2>nul | findstr /i /x "gd" >nul && goto gdok
set "PHPDIR="
if /i "%PHPEXE%"=="php" (for /f "delims=" %%P in ('where php') do if not defined PHPDIR set "PHPDIR=%%~dpP") else (for %%P in ("%PHPEXE%") do set "PHPDIR=%%~dpP")
if not exist "%PHPDIR%ext\php_gd.dll" for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3*") do if exist "%%D\ext\php_gd.dll" set "PHPDIR=%%D\"
if exist "%PHPDIR%ext\php_gd.dll" set PHPOPT=-d extension_dir="%PHPDIR%ext" -d extension=gd
:gdok

echo   Webseite:      http://localhost:8123
echo   Admin-Bereich: http://localhost:8123/admin/
echo   Beim ersten Oeffnen des Admins legst du dein Passwort fest
echo   ^(der Einrichtungscode wird beim lokalen Test direkt angezeigt^).
echo.
echo   Zum Beenden dieses Fenster schliessen.
echo.
start "" "http://localhost:8123/"
start "" "http://localhost:8123/admin/"
"%PHPEXE%" %PHPOPT% -d upload_max_filesize=200M -d post_max_size=210M -S localhost:8123 _reserved/testserver.php
pause
