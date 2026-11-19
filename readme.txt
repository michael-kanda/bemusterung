=== MXO Bemusterung ===
Contributors:      designare
Tags:              music, promo, download, upload, audio
Requires at least: 6.5
Tested up to:      6.8
Requires PHP:      7.4
Stable tag:        0.5.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Selbstverwaltete Promoseiten für Musikbemusterungen. Externe verwalten alles über einen Link, ohne WordPress-Konto.

== Description ==

Wer einen Zugangslink hat, verwaltet seine Bemusterungen vollständig selbst: neue Bemusterung anlegen, Artist und Pressetext eintragen, Cover, Vorhör-MP3 und Master-WAV per Drag and Drop hineinziehen, veröffentlichen und den fertigen Link kopieren. Alles im Frontend, ohne Backend und ohne WordPress-Konto.

Der Zugangslink gehört zu einer Person, nicht zu einer einzelnen Bemusterung. Er gilt dauerhaft und lässt sich jederzeit zurückziehen, ohne dass angelegte Inhalte verloren gehen. Jeder Zugang sieht ausschließlich seine eigenen Bemusterungen.

Die fertigen Promoseiten sind über ihren Link erreichbar, aber nicht auffindbar: kein Archiv, kein Suchtreffer, keine Sitemap, noindex. Empfänger melden sich nirgends an.

Eine Bemusterung kann mehrere Titel enthalten. Audiodateien mit gleichem Dateinamen werden automatisch demselben Titel zugeordnet. Die Vorhörversion läuft als MP3 direkt auf der Seite, die Masterdatei liegt in einem abgeschirmten Ordner und wird nur über einen Endpunkt ausgeliefert, der die Downloads mitzählt.

Die Promoseite wird fertig ausgeliefert und braucht keine Theme-Arbeit: Cover, Kopfdaten, Abspieler je Titel, WAV-Download und Pressetext. Wer lieber selbst gestaltet, schaltet das unter Einstellungen ab. Alle Felder sind als Post Meta registriert und stehen dem Block-Editor sowie Dynamic Data zur Verfügung, die Titelliste ist ein eigener Inhaltstyp für den Query Loop, und der Shortcode [mxo_bemusterung_download] liefert den Downloadbutton.

Gezählt werden ausschließlich Summen. Es werden keine IP-Adressen, Zeitstempel oder Besucherdaten gespeichert. Das Rückmeldeformular ist freiwillig und sperrt den Download nicht.

== Installation ==

1. Den Ordner nach wp-content/plugins hochladen.
2. Das Plugin aktivieren.
3. Eine Seite anlegen, dort den Shortcode [mxo_bemusterung_portal] einsetzen und veröffentlichen.
4. Unter Bemusterungen / Zugänge einen Zugang anlegen und den erzeugten Link weitergeben.

Die Portalseite wird automatisch erkannt. Nur wenn der Shortcode auf mehreren Seiten steht, muss unter Bemusterungen / Einstellungen ausgewählt werden, welche gilt. Dort stehen auch maximale Dateigröße, Auslieferungsart und die Adresse für Benachrichtigungen.

== Frequently Asked Questions ==

= Braucht die externe Person ein WordPress-Konto? =

Nein. Der Zugangslink genügt für die vollständige Verwaltung.

= Was passiert, wenn der Zugangslink weitergegeben wird? =

Dann kann der Empfänger dieselben Bemusterungen verwalten. Zieh den Zugang in dem Fall zurück und lege einen neuen an. Die Inhalte bleiben erhalten.

= Muss sich der Empfänger einer Bemusterung anmelden? =

Nein. Wer den Promolink hat, kann vorhören und herunterladen.

= Ist die Promoseite damit wirklich privat? =

Sie ist nicht auffindbar, aber nicht zugriffsgeschützt. Wer den Link weitergibt, gibt den Zugriff weiter.

= Warum bricht der Upload großer WAV-Dateien ab? =

Meist wegen upload_max_filesize, post_max_size oder max_execution_time in PHP. Diese Werte müssen zur Dateigröße passen. Die Einstellungsseite warnt, wenn das Serverlimit unter der eingestellten Größe liegt.

= Kann ich den Link zur Musikdatei direkt verschicken? =

Ja. Jeder Titel hat eine eigene Adresse der Form /bemusterung/<release>/download/<titel>/. Sie steht im Portal bei jedem Titel zum Kopieren bereit, zusammen mit der Adresse der Vorhörversion. Beide zählen beziehungsweise funktionieren ohne Anmeldung.

= Der Download großer Dateien ist langsam. =

Standardmäßig liefert PHP die Datei aus. Wenn der Webserver X-Accel-Redirect oder X-Sendfile beherrscht, lässt sich das unter Einstellungen umstellen.

== Changelog ==

= 0.5.1 =
* Bemusterungen sind jetzt nur noch über ihren Link erreichbar. Neben dem robots-Meta sendet die Promoseite einen X-Robots-Tag-Header, damit SEO-Plugins das noindex nicht mehr überschreiben.
* All in One SEO, Yoast und Rank Math: noindex auf Promo- und Portalseite, Bemusterungen und Portalseite aus den Sitemaps, bei AIOSEO auch aus der llms.txt.
* Keine Auflistung mehr über ?post_type=, Feeds, Anhangseiten oder die WordPress-REST-API (Beiträge, Tracks, Mediathek, Suche). Die Mediathek-API hat bisher den geheimen Ablagepfad der WAV-Master verraten.
* Der Aufruf über die Beitrags-ID (?p=123) liefert für Besucher 404, sonst ließen sich die Seiten durch Hochzählen finden.

= 0.5.0 =
* Jeder Titel hat jetzt eine eigene, sprechende Download-Adresse der Form /bemusterung/<release>/download/<titel>/, die sich direkt in eine Mail an die Redaktion setzen lässt.
* Im Portal stehen Download- und Vorhörlink je Titel zum Kopieren bereit.
* Die Schreibregeln werden nach dem Update automatisch erneuert, ein Speichern unter Permalinks ist nicht nötig.
* Ohne sprechende Permalinks bleibt die bisherige Adresse mit Abfragevariable gültig.

= 0.4.0 =
* Die Promoseite wird jetzt fertig ausgeliefert: Cover, Artist, Label, Releasedatum, Abspieler je Titel, WAV-Download mit Format- und Größenangabe, Presskit und Pressetext. Ohne Theme-Arbeit.
* Auch die Promoseite bringt eigene Farben mit und ist damit auf dunklen Themes lesbar.
* Neuer Shortcode [mxo_bemusterung_download] für eigene Layouts.
* Neue Einstellung, um das mitgelieferte Layout abzuschalten und stattdessen selbst im Theme zu bauen.

= 0.3.5 =
* Das Feld mit dem Bemusterungslink blieb dunkel eingefärbt, weil ihm das type-Attribut fehlte und der Selektor deshalb nicht griff. Die Feldregeln hängen jetzt nicht mehr am type-Attribut.

= 0.3.4 =
* Eingabefelder, Buttons und Beschriftungen setzen ihre Farben jetzt gegen das Theme durch. Auf GeneratePress blieben die Felder vorher dunkel eingefärbt.
* Mehr Abstand nach oben, damit das Panel nicht unter einem überlagernden Header beginnt.

= 0.3.3 =
* Das Portal bringt eigene Farben mit, statt die Global Colors des Themes zu verwenden. Auf dunklen Themes waren Beschriftungen vorher praktisch unlesbar.
* Das Portal sitzt jetzt in einem eigenen Panel mit Abstand nach oben, damit es nicht unter einem transparenten oder klebenden Header verschwindet.
* Formularfelder und Buttons setzen ihre Darstellung selbst, damit Theme-Styles sie nicht verformen.

= 0.3.2 =
* Behebt, dass gültige Zugangslinks als ungültig abgewiesen wurden. Die Zugangs-ID enthält Großbuchstaben, wurde bei der Prüfung aber in Kleinbuchstaben umgewandelt. Bestehende Links funktionieren nach dem Update wieder.
* Zugangslinks werden nicht mehr doppelt kodiert.

= 0.3.1 =
* Behebt einen kritischen Fehler: das Rückmeldeformular verwies auf eine Konstante, die beim Umbau auf das Portal weggefallen war. Dadurch brach jeder Aufruf der REST-API ab, unter anderem beim Anlegen einer neuen Seite im Block-Editor.

= 0.3.0 =
* Vollständige Selbstverwaltung im Frontend: anlegen, bearbeiten, Dateien hinzufügen, veröffentlichen, Link kopieren, Downloadzahlen sehen.
* Zugangslinks gehören jetzt zu einer Person und gelten dauerhaft, statt an eine einzelne Bemusterung gebunden und befristet zu sein.
* Jeder Zugang sieht nur seine eigenen Bemusterungen.
* Titel lassen sich im Portal vorhören und entfernen, ersetzte Dateien werden aufgeräumt.
* Uploads laufen nacheinander statt parallel, Benachrichtigungen werden auf eine pro Stunde und Bemusterung gedrosselt.
* Neuer Shortcode [mxo_bemusterung_portal]. Der alte Name funktioniert weiter.

= 0.2.0 =
* Einstellungsseite statt Konfiguration über Filter im Code.
* Automatische Erkennung der Portalseite, noindex und Sitemap-Ausschluss.
* Warnung, wenn die eingestellte Dateigröße über dem PHP-Limit liegt.

= 0.1.0 =
* Erste Fassung.

== Upgrade Notice ==

= 0.5.1 =
Macht Bemusterungen unauffindbar für Suchmaschinen, Sitemaps, llms.txt und die REST-API.

= 0.5.0 =
Bringt eigene Download-Links je Titel für den Versand an Redaktionen.

= 0.4.0 =
Die Promoseite funktioniert jetzt ohne Theme-Arbeit.

= 0.3.5 =
Behebt die Darstellung des Linkfelds.

= 0.3.4 =
Behebt die Darstellung der Formularfelder auf Themes mit eigenen Formularfarben.

= 0.3.3 =
Behebt Lesbarkeit und Positionierung des Portals auf dunklen Themes.

= 0.3.2 =
Behebt die Zugangsprüfung. Bestehende Links gelten weiter.

= 0.3.1 =
Behebt einen kritischen Fehler in 0.3.0. Update dringend empfohlen.

= 0.3.0 =
Der Zugang ist jetzt ein dauerhafter Link pro Person, mit dem im Frontend alles selbst verwaltet wird. Bestehende Einmal-Token entfallen.
