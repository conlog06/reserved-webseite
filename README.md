# Reserved. – Webseite

One-Page-Webseite für **Reserved. – Social Media für lokale Unternehmen**, mit Admin-Bereich, Instagram-Live-Feed, smarter Anfrage und eigener Statistik (ohne Cookies).

Gebaut nach demselben Prinzip wie die LoggX-Seite: **PHP ohne Datenbank** (Daten liegen geschützt in Dateien), läuft auf jedem Webspace mit PHP 8.1+ (z. B. IONOS). Ohne PHP (z. B. GitHub Pages) funktioniert die Seite trotzdem – dann mit festen Inhalten, Beispiel-Feed und Formular per E-Mail-Programm.

## Funktionen

**Webseite**
- Instagram direkt angebunden: Profil (Beiträge, Follower), Raster der neuesten Beiträge wie in der App, Großansicht mit Karussell/Video, Likes, Kommentaren und Caption. Bilder werden auf den eigenen Server kopiert (schnell + datenschutzfreundlich), Abgleich alle 30 Minuten.
- Smarte Anfrage in 3 Schritten (Branche → Ziele → Kontakt) mit **Live-Paket-Empfehlung**, Bestätigungs-Mail an die Kund:in, Spam-Schutz (Honeypot, Zeitsperre, Rate-Limit).
- Für Handys optimiert: Notch/Safe-Area (iPhone & Android), feste Anfrage-Leiste über der Home-Leiste, 16-px-Eingabefelder (kein Zoom auf iOS), große Touch-Flächen, Vollbild-Großansicht, kleine Displays (iPhone SE) und Querformat, App-Icon für den Homescreen.

**Admin-Bereich** (`/admin/`, am Handy mit App-Navigation unten)
- **Übersicht:** Besucher, Anfragen, Anfrage-Quote, Verweildauer, Tagesverlauf, automatische Hinweise („Instagram bringt die meisten Besucher“, „beste Uhrzeit für Posts“, heiße Anfragen …).
- **Anfragen:** Postfach mit Priorität (heiß/warm/kalt), Paket-Empfehlung, Status (Neu → Kontaktiert → Angebot → Kunde/kein Auftrag), Notizen, Ein-Klick-Antwort per E-Mail/Anruf/WhatsApp/Instagram, CSV-Export, automatische Löschfrist.
- **Statistik:** Herkunft (inkl. Instagram-App ohne Referrer), Kampagnen (`utm_campaign`), Scroll-Tiefe pro Abschnitt, Formular-Trichter, Klicks (welches Paket angefragt), Geräte/Systeme/Browser, Uhrzeiten, Wochentage, Instagram-Follower-Verlauf, Interaktionsrate, beliebteste Beiträge.
- **Inhalte:** Hero-Texte, Kennzahlen, Ankündigungsleiste, Paket-Tabelle (Zeilen, Namen, Preise), Zusatzleistungen, Ergebnis-Karten, Kundenstimmen (Filter-Buttons entstehen automatisch), Über-uns-Text und -Bild.
- **Instagram:** Zugangsschlüssel eintragen (Anleitung im Admin), Anzahl Beiträge, Jetzt aktualisieren.
- **Einstellungen:** Online/Wartung, E-Mail-Empfänger & Absender, Test-Mail, Impressum, Datenschutzerklärung (Vorlage oder eigener Text), Statistik an/aus, Passwort.

## Einrichtung (einmalig)
1. Alle Dateien per FTP/SFTP in das Web-Verzeichnis hochladen – **inklusive der versteckten `.htaccess`-Dateien**. PHP auf 8.1 oder neuer stellen.
2. Schreibrechte für `_reserved/data/`, `assets/ig/` und `assets/img/` (bei IONOS Standard).
3. `https://deine-domain.de/admin/` öffnen → der Einrichtungscode steht in `_reserved/data/einrichtungscode.php` (Webspace-Explorer oder FTP) → Passwort festlegen.
4. Unter **Einstellungen** eine Absender-Adresse der eigenen Domain eintragen (z. B. `noreply@deine-domain.de`) und eine Test-Mail schicken.
5. Unter **Instagram** den Zugangsschlüssel eintragen (Schritt-für-Schritt-Anleitung im Admin).

## Lokal testen
**Windows:** `Test starten.bat` doppelklicken (installiert PHP bei Bedarf einmalig über winget).

**Sonst:**
```
php -S localhost:8123 _reserved/testserver.php
```
Seite: http://localhost:8123 · Admin: http://localhost:8123/admin/ (Einrichtungscode wird lokal direkt angezeigt, E-Mails landen in `_reserved/data/mail-test.log`).

## Struktur
```
index.html             Seite (Inhalte mit <!--cms:…--> / data-cms werden vom Server ersetzt)
assets/                CSS, JS, Schriften (lokal, kein Google Fonts), Bilder, ig/ (Instagram-Cache)
_reserved/             PHP: lib, gate (Seite ausliefern), api (Anfragen/Statistik), instagram, leads, stats, render
_reserved/data/        Laufzeitdaten – gesperrt, nicht im Git
admin/                 Admin-Bereich
```

Farben: Creme #F4F1EA · Sand #EFE9DC · Terrakotta #B8593F · Kohle #2B2622 · Hellblau #A9BFD4 · Rostbraun #984619 · Mokka #6B4A3A · Warmgrau #7A7269 · Trennlinie #DCD5C8
