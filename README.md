# reserved.social – Webseite

One-Page-Webseite für **reserved.social – Social Media für lokale Unternehmen**, mit Admin-Bereich, Instagram-Live-Feed, smarter Anfrage und eigener Statistik (ohne Cookies).

Gebaut nach demselben Prinzip wie die LoggX-Seite: **PHP ohne Datenbank** (Daten liegen geschützt in Dateien), läuft auf jedem Webspace mit PHP 8.1+ (z. B. IONOS). Ohne PHP (z. B. GitHub Pages) funktioniert die Seite trotzdem – dann mit festen Inhalten, Beispiel-Feed und Formular per E-Mail-Programm.

## Funktionen

**Webseite** (Aufbau: Start-Kacheln → Für wen → Rhythmus → Leistungen → Instagram → Vergleich → Stimmen → Monatspakete → Über uns → Kontakt)
- Startbereich als Kachel-Raster: großer Titel „Dein Platz im Feed ist reserviert.“, Handy mit Live-Beiträgen, Wochenrhythmus, die 4 neuesten Instagram-Beiträge, Kennzahlen und Frage-Kachel.
- „Rhythmus“: Jahresgrafik (ohne vs. mit festem Rhythmus), Monate und Kalenderwoche passen sich automatisch an das heutige Datum an.
- Alle Überschriften und Texte der Abschnitte sind im Admin unter **Inhalte** änderbar.
- Instagram direkt angebunden: Profil (Beiträge, Follower), Raster der neuesten Beiträge wie in der App, Großansicht mit Karussell/Video, Likes, Kommentaren und Caption. Bilder werden auf den eigenen Server kopiert (schnell + datenschutzfreundlich), Abgleich alle 30 Minuten.
- Smarte Anfrage in 3 Schritten (Branche → Ziele → Kontakt) mit **Live-Paket-Empfehlung**, Bestätigungs-Mail an die Kund:in, Spam-Schutz (Honeypot, Zeitsperre, Rate-Limit).
- Für Handys optimiert: Notch/Safe-Area (iPhone & Android), feste Anfrage-Leiste über der Home-Leiste, 16-px-Eingabefelder (kein Zoom auf iOS), große Touch-Flächen, Vollbild-Großansicht, kleine Displays (iPhone SE) und Querformat, App-Icon für den Homescreen.

**Admin-Bereich** (`/admin/`, am Handy mit App-Navigation unten)
- **Übersicht:** Besucher, Anfragen, Anfrage-Quote, Verweildauer, Tagesverlauf, automatische Hinweise („Instagram bringt die meisten Besucher“, „beste Uhrzeit für Posts“, heiße Anfragen …).
- **Anfragen:** Postfach mit Priorität (heiß/warm/kalt), Paket-Empfehlung, Status (Neu → Kontaktiert → Angebot → Kunde/kein Auftrag), Notizen, Ein-Klick-Antwort per E-Mail/Anruf/WhatsApp/Instagram, CSV-Export, automatische Löschfrist.
- **Statistik:** Herkunft (inkl. Instagram-App ohne Referrer), Kampagnen (`utm_campaign`), Scroll-Tiefe pro Abschnitt, Formular-Trichter, Klicks (welches Paket angefragt), Geräte/Systeme/Browser, Uhrzeiten, Wochentage, Instagram-Follower-Verlauf, Interaktionsrate, beliebteste Beiträge.
- **Inhalte:** Hero-Texte, Kennzahlen, Ankündigungsleiste, Paket-Tabelle (Zeilen, Namen, Preise), Zusatzleistungen, Ergebnis-Karten, Kundenstimmen (Filter-Buttons entstehen automatisch), Über-uns-Text und -Bild.
- **Instagram:** Beiträge selbst hochladen (Bild, Karussell oder Reel-Video, Text, Link, Datum, Likes) – oder automatisch per Zugangsschlüssel. Anzahl Beiträge, Follower-Zahl.
- **Einstellungen:** Online/Wartung, E-Mail-Empfänger & Absender, Test-Mail, Impressum, Datenschutzerklärung (Vorlage oder eigener Text), Statistik an/aus, Passwort.

**Animationen & Scrollen**
- [GSAP](https://github.com/greensock/gsap) mit ScrollTrigger & SplitText, [Lenis](https://github.com/darkroomengineering/lenis) für weiches Scrollen (am Handy bleibt natives Scrollen).
- Nachbauten aus [React Bits](https://github.com/DavidHDev/react-bits) als Vanilla-JS: ScrollFloat (Überschriften), ScrollReveal (Text füllt sich), RotatingText, CountUp, ScrollVelocity (Laufband reagiert aufs Scrollen), Magnet, SpotlightCard, ClickSpark, TiltedCard.
- Nur `transform`/`opacity`, kein Pinning; bei „Bewegung reduzieren“ oder ohne JavaScript läuft die Seite normal ohne Effekte.

**Seiten (im Admin unter „Seiten“)**
- **Vor-Launch:** Countdown-Seite mit laufender Bildwand, E-Mail-Benachrichtigung, Zugangslinks für Vorschau (z. B. für Victoria), automatischer Start zum Zeitpunkt inkl. Benachrichtigungs-Mail an alle Eingetragenen (danach werden die Adressen gelöscht).
- **Landingpage `/angebot/`:** fokussierte Werbeseite (ohne Menü) mit Aktion inkl. Countdown, Vorteilen, Ergebnissen, Instagram, Paketkarten, Stimmen, FAQ und Formular. Texte, Aktion, FAQ und Abschnitte im Admin; fertige Links mit Herkunft (Instagram-Bio, Story, Flyer …) für die Statistik.
- **Fit-Check `/start/` – „Zusammenarbeit starten“:** persönliche Begrüßung, eine Frage pro Bildschirm (Unternehmen, Instagram, Branche, Ziele, Follower, Posten, Umfang, Start, Budget-Spanne, Nachricht, Kontakt), Antippen oder Buchstabentaste, automatisch weiter, Fortschrittsbalken, Paket-Empfehlung am Ende. Ideal als Link in der Instagram-Bio. Im Admin: Texte, Profilfoto, Abbruch-Trichter je Frage, Links mit Herkunft.
- **Über mich `/ueber-mich/`:** Victorias Weg (UGC → Westerberg Chaya → Gründerin) als Zeitstrahl, Stationen und Jahreszahlen im Admin unter „Inhalte“.
- **UGC-Seite `/ugc/` (Creator Kit):** Victoria als Creatorin (Westerberg-Chaya) – Reichweite, Zielgruppe Instagram/TikTok mit Balken, Beispielvideos (Upload im Admin, erscheinen erst mit dem ersten Video), drei Leistungen „auf Anfrage“, Ablauf, Marken, Kontakt per Mail. Alles im Admin unter „Seiten → UGC-Seite“.
- **Reel-Statistiken:** echte Zahlen (Aufrufe, Likes, Geteilt) zu Reels für bekannte Kund:innen – der Abschnitt erscheint erst, wenn im Admin Einträge stehen.
- **Wartung:** Hinweisseite (HTTP 503).

**Funnel „Content-Plan Starter“ (Admin → Funnel)**
- `/plan/`: kostenloser PDF-Download gegen Name + E-Mail (Einwilligung, Spam-Schutz, Herkunft per `utm_source`).
- Zustellung im Admin wählbar: **direkt auf der Webseite**, **beides** (sofort + Link per Mail, Tipps per Mail nur nach Bestätigung) oder **nur per Mail** (Double-Opt-in).
- Double-Opt-in: Bestätigungsmail → nach dem Klick `/plan/danke/` mit Download, Angebot „Strategie jeden Monat mit Victoria – ab … €“, Termin-Button (Calendly/Cal.com-Link) und kostenlosem Erstgespräch. Zusätzlich Mail mit Download-Link und Abmeldelink.
- Admin: alle Texte, Preis, eigenes PDF hochladen (sonst Platzhalter), Einträge mit Status + CSV-Export, Zahlen (eingetragen, bestätigt, geladen, Termin geklickt), Links zum Teilen.

**Termine (Admin → Termine)** – eigenes System, kein Calendly, keine Kosten
- Victoria schlägt einen Termin vor (Name, E-Mail, Terminart, Datum, Uhrzeit, Ort, persönliche Nachricht) – auch direkt aus einer Anfrage („Termin vorschlagen“).
- Die Person bekommt eine anpassbare E-Mail (Vorlage mit Platzhaltern) mit Kalender-Datei (.ics) im Anhang und einem Link zu `/termin/?t=…`: „Ja, passt mir“ oder „Passt leider nicht“ mit Nachricht.
- Victoria bekommt eine Mail bei jeder Antwort; im Admin: Status, Erinnern, Verschieben (neuer Vorschlag), Absagen (Kalendereintrag wird entfernt).

**UGC-Anfragen:** Formular im E-Mail-Look auf `/ugc/` (An/Von/Marke/Betreff/Nachricht, Schnellauswahl der Leistungen) → Mail an die Booking-Adresse, Eingangsbestätigung an die Marke, Liste im Admin unter „UGC“.

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
