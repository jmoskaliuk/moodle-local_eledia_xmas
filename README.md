# eLeDia | Xmas

Moodle-Plugin, Beta 0.3.1, für Moodle 4.5 bis 5.2 (PHP 8.1+).

## Installation

ZIP über Website-Administration → Plugins → Plugin installieren hochladen.
Alternativ den Ordner `eledia_xmas` nach `local/` kopieren; ab Moodle 5.1 unter `public/local/`.
Danach Moodle-Upgrade ausführen und Caches leeren. Bei Updates immer auch den Sprach- und JavaScript-Cache leeren.

Einstellungen: Website-Administration → Plugins → Lokale Plugins → eLeDia | Xmas.
Das Plugin ist anfangs deaktiviert. Die Admin-Vorschau funktioniert unabhängig von Aktivierung und Zeitraum.

## Funktionen

- Canvas-Schneefall, der nach Inaktivität zum Schneesturm wird.
- Lokale Ablagerung, unterschiedlich hohe Schneeberge und seitliches Abrutschen.
- Schnee wächst von der unteren Bildkante nach oben: auf Kurskacheln und dem Boost-Union-Kursbanner.
  Ungleichmäßige Hügel bleiben innerhalb des Bildes (höchstens 24 % der Bildhöhe bzw. 56 Pixel).
- Schneemützen auf bildlosen Kurskacheln und Aktivitätsicons (bis 27 bzw. 17 Pixel).
- Eingaben räumen die Sicht in 900 ms frei und werden nicht abgefangen.
- Jährlicher Zeitraum in der Website-Zeitzone, auch über den Jahreswechsel; inklusive Start-/Endtag.
- Startseite, Dashboard und Meine Kurse; Kursübersichten optional. Keine Effekte auf Aktivitäts-, Prüfungs-, Login- oder Administrationsseiten und im Bearbeitungsmodus.
- Vollständiges Zuschneien optional. Standardmäßig niedrige Schneeberge.
- Bei nahezu vollständiger Bedeckung erscheint ein gut lesbarer Hilfehinweis über dem Schnee.
  Drei Texte wechseln pro Schneepause; Mausbewegung, Berührung oder Tastatureingabe blenden den Hinweis
  sofort aus und räumen die Seite frei. Der Hinweis nimmt keinen Fokus und fängt keine Eingaben ab.
- Persönlicher Ein-/Ausschalter im Browserspeicher, keine Datenbanktabellen und keine externen Dienste.
- Reduzierte Bewegung wird berücksichtigt; manuelles Einschalten bleibt möglich. Unsichtbare Tabs pausieren.
- Die Easter Eggs liegen an festen Stellen am unteren Rand von Kursbildern oder dem Kursbanner und scrollen mit.
  Ohne passende Bildfläche bleiben sie verborgen; eigene Themes können `data-xmas-ledge` als Ablage anbieten.
- Drei optionale Entdeckungen während der Winterpause: Schneeflocken mit Weihnachtsmann, Rentier und Schneemann.
- Grüße als Text; freiwilliges Anhören als lokale MP3 oder mit einer lokalen Browser-/Gerätestimme, keine automatische Sprachausgabe.
- Deutsch und Englisch.

## Vorschau und Gimmicks

In den Plugin-Einstellungen „eLeDia | Xmas ausprobieren“ wählen. Die Vorschau startet auf der echten Startseite
und gilt nur für den Administrator in der aktuellen Sitzung (maximal eine Stunde). Über die Vorschauleiste
zum Dashboard, zu „Meine Kurse“ oder anschließend in einen echten Kurs wechseln. Prüfungs- und Bearbeitungsseiten
bleiben ausgeschlossen. „Vorschau beenden“ kehrt zu den Einstellungen zurück.
Schnellmodus aktivieren, Winterpause testen und den Mauszeiger ruhig halten.

„Schneekontrast“ bietet **Dezent** (Standard) und **Deutlich** mit blaugrauer Unterkante, feiner Kontur und
stärkerem Kontaktschatten für helle Hintergründe. Die Einstellung wirkt auch auf die Schneeberge am Fensterrand.

Die drei Schneeflocken erscheinen nach 3, 6 und 9 Sekunden Winterpause im Schnellmodus,
sonst nach 12, 24 und 36 Sekunden. Sie bleiben anschließend anklickbar. Escape oder „Überraschung schließen“ beendet den Gruß.

## Theme-Unterstützung

Die Erkennung verwendet `.dashboard-card`, `.coursebox` und `.activityiconcontainer` aus Boost-basierten Oberflächen.
Eigene Themes können `data-xmas-image` für Schnee innerhalb eines Bildbereichs, `data-xmas-ledge` an geeigneten Elementen sowie zusätzlich `data-xmas-icon` an Icons setzen.
Asynchron geladene Kacheln werden erkannt. Geometrie folgt Scrollen und Layoutänderungen.
Beliebige Custom-Themes benötigen einen visuellen Kompatibilitätstest. Das Moodle-App-Frontend wird nicht dekoriert.

## Entwicklung

`amd/src/` enthält die Quellmodule, `amd/build/` die mit Moodles Grunt-Toolchain erzeugten auslieferbaren Module.
Build aus dem Moodle-Root: `grunt amd --root=public/local/eledia_xmas` (bei 4.5 ohne `public/`).
Dazu die Node-Version aus Moodles `.nvmrc` verwenden. Die installierbare ZIP enthält keine Node-Abhängigkeiten.
PHPUnit deckt Jahreswechsel, Zeitzonen, Schaltjahre, Konfigurationsgrenzen, Seitenausschlüsse und Vorschau-Berechtigung ab.

## Grenzen dieser Beta

Keine automatischen Weihnachtsnachrichten, Adventskalender oder echte physikalische Schneesimulation.
Schneemengen sind für den visuellen Effekt verstärkt, maximal 1.600 Flocken und 150 Oberflächen.
Browserpräferenzen werden nach Website und angemeldetem Nutzer getrennt, nicht im Moodle-Nutzerprofil gespeichert.

Lizenz: GNU GPL v3 oder neuer. © 2026 eLeDia GmbH.

## Kleine Winterbelohnungen

Alle Fundstücke erscheinen als Schneeflocken. Mit der Maus hüpfen sie genau einmal; Touch,
Tastaturfokus und reduzierte Bewegung umgehen die Bewegung. Die Figuren bleiben als Überraschung erhalten.

Der Noten-Schalter aktiviert kurze, leise Jingles. MP3-Dateien liegen im Plugin und werden von Moodle ausgeliefert;
ElevenLabs wird nur bei der Produktion verwendet, nie beim Lernen. Fehlende oder nicht ladbare Jingles
werden durch lokal erzeugte Glockentöne ersetzt.
Moodles bestätigtes `manualCompletionToggled`-Ereignis feiert erledigte Aktivitäten.
Neue sichtbare Quiz-Bestehenszustände werden beim erneuten Besuch der Kursübersicht erkannt,
wenn zuvor dort ein noch nicht bestandenes Quiz erfasst wurde. Keine Feier während eines Versuchs,
bei versteckten Bewertungen, ohne Bestehensgrenze oder für bereits beim ersten Besuch bestandene Quizze.
Verzögerte Bewertungen werden beim nächsten Kursbesuch berücksichtigt.

Browserlokale Kennungen pro Nutzer und Saison verhindern Wiederholungen; es werden keine
Noten gespeichert. Nach Löschen des Browserspeichers können Aktivitäten erneut gefeiert werden.
Ton ist anfangs aus. Browser benötigen nach Seitenwechsel einen Nutzerklick zur Audiofreigabe;
ohne Freigabe bleibt der Erfolgsgruß visuell. Ein Klick auf den Noten-Schalter spielt einen Probeton.

## Zeitraum und Sprache

Beginn und Ende stehen jeweils in einer Zeile mit Tag und ausgeschriebenem, übersetztem Monat.
Die Auswahl wiederholt sich jährlich und benötigt keine Jahreszahl. Ungültige Kombinationen werden beim
Speichern abgewiesen; der 29. Februar gilt in anderen Jahren am Monatsende. Bestehende Einstellungen bleiben erhalten.
Alle UI-Texte kommen aus den Sprachdateien. Deutsch verwendet durchgehend die Du-Form.

## Weitere Belohnungen und Aufnahmen

- Der erste Klick auf jede entdeckte Figur kann einen Jingle auslösen.
- Automatische Aktivitätsabschlüsse und Kursabschlüsse werden beim nächsten Besuch der Kursübersicht erkannt,
  wenn zuvor ein offener Zustand erfasst wurde. Bereits beim ersten Besuch abgeschlossene Inhalte bleiben still.
- Es zählen nur sichtbare Aktivitäten für aktiv eingeschriebene Nutzer. Versteckte Bewertungen lösen keine
  Aktivitätsfeier aus; ein fehlgeschlagener Abschluss wird nicht als Erfolg gefeiert.
- Gleichzeitig erkannte Erfolge erhalten nur eine Meldung: Kursabschluss vor bestandenem Test vor Aktivität.
  Jingles haben mindestens fünf Sekunden Abstand. Die Tonfreigabe gilt auch für diese Anlässe.
- Die deutschen Figuren-Grüße sind auf Klick als MP3 verfügbar. Andere Sprachen und individuell angepasste
  Texte verwenden eine verfügbare lokale Gerätestimme. `audio/transcripts.json` bindet jede Aufnahme an ihren
  exakten Text. Bei Änderungen an der Sprachdatei muss die Aufnahme neu erzeugt werden.
- Ein-/Ausschalten, versteckte Tabs und Schließen eines Grußes beenden die jeweilige Wiedergabe.

Browserlogik prüfen: `node --test tests/js/celebrations_test.cjs`.
