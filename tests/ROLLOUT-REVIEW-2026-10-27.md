# Lokale Rollout-Prüfung vom 27. Oktober 2026

**Entscheidung: No-Go für den vereinbarten Rollout mit reinem Code-Rollback.**

Geprüft wurde `dev` bei `a56ae9398080f95da81204c4c0f99ed8ae4ad4e0` gegen den
auf GitHub bestätigten, für alle Vergleiche festgehaltenen `main`-Commit
`3b458c812ba61c1f9b8beac22022cfd7b159ba58`. Im Rahmen dieser Prüfung wurden
Produktivcode und öffentliche APIs nicht geändert. Die Änderungen ergänzen ausschließlich Tests und deren
Dokumentation. Es gab kein Deployment und keine Fehlerbehebung.

Die CMS-Migration gilt als abgeschlossen. Die Prüfung arbeitet ausschließlich
mit synthetischen Daten in `rrze_answers_tests`, getrennt von der regulären
lokalen Installation. Der Upgrade-Test erzeugt zwei initialisierte Sites mit
allen vier Inhaltstypen, lokalen/importierten Einträgen, Optionen und bereits
gesetzten Migrationsmarkern. Die historische CMS-Migration wurde nicht erneut
auf einer bestehenden Site ausgeführt.

## Freigabeblockierende Befunde

### R1 – P1: Nach Editor-Speichern ist der Code-Rollback nicht ausgabesicher

- **Kombination:** `main → dev → Editor-Save → main`, jeweils zwei Sites auf
  WordPress 7.1.3 und zusätzlich 6.8, beide mit PHP 8.5.12.
- **Reproduktion:** `RRZE_UPGRADE_REFERENCE=main RRZE_UPGRADE_ROLLBACK=1 composer test:upgrade`.
  Legacy-Blöcke mit gezielter Auswahl unter main erzeugen, mit der aktuellen
  Gutenberg-Registrierung parsen/serialisieren und speichern, danach echtes
  main-PHP in einem frischen Prozess auf dieselbe Datenbank laden.
- **Erwartet:** Auswahl, Filter und Darstellung bleiben bei reinem Codewechsel
  erhalten.
- **Tatsächlich:** main verwirft typfremde Array-Attribute. FAQ, Glossary, Synonym
  und Placeholder fallen dadurch auf eine breitere Auswahl zurück; eigens
  angelegte unselektierte Sentinel-Einträge erscheinen zusätzlich. Das FAQ-Widget
  verliert den booleschen `hide_title`-Wert und wechselt von flacher Ausgabe zu
  einem Akkordeon. Dies ist kein Löschen von Datensätzen; die gespeicherte
  Auswahl wird von der alten Version nicht mehr verstanden.
- **Code:** `src/editor/migrations/legacy-attributes.ts:117` und die aktuellen
  `src/blocks/*/block.json`-Schemas; main erwartet für Listen Strings und beim
  Widget eine Zahl. Die neue Registrierung ist vorwärtskompatibel, die neue
  Serialisierung nicht rückwärtskompatibel.
- **Abgrenzung:** Ohne Editor-Save besteht der Rollback. Auch ein separater Lauf
  mit erstem und wiederholtem Sync **ohne** Editor-Save besteht auf beiden
  WordPress-Versionen. Die Daten-Snapshots ändern sich beim Rollback nicht.
  Klassische Shortcodes und bestehende Inline-Formate behalten ihre Ausgabe.
- **Notwendig:** Einen mit main lesbaren Speichervertrag oder eine ausdrücklich
  getestete Rückkonvertierung bereitstellen. Unter der vereinbarten Bedingung
  „ausschließlich Plugin-Code zurücksetzen“ ist der aktuelle Stand nicht frei.

### R2 – P1: FAQ-Editor verwendet einen auf WordPress 6.8 fehlenden Export

- **Kombination:** ausgeliefertes FAQ-Bundle mit dem unveränderten offiziellen
  WordPress-6.8-Asset `server-side-render.js`.
- **Reproduktion:**
  `RRZE_ROLLOUT_WP68_SSR=/private/tmp/rrze-answers-rollout-wp68/wp-includes/js/dist/server-side-render.js npm run test:unit -- --runInBand --watch=false tests/js/rollout-editor-compatibility.test.js`.
- **Erwartet:** Die FAQ-Vorschau erhält eine gültige React-Komponente.
- **Tatsächlich:** `wp.serverSideRender.ServerSideRender` ist `undefined`; das
  FAQ-Bundle erzeugt einen ungültigen Komponententyp. Die vier übrigen Bundles
  bestehen denselben Vertragstest.
- **Code:** `src/blocks/faq/edit.tsx:4`; der Fehler ist auch im eingecheckten
  `build/blocks/faq/index.js` enthalten. main verwendet den Default-Import.
- **Quelle:** Die [WordPress-Dokumentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/)
  verlangt für WordPress 6.8 und älter den Default-Import.
- **Prüfgrenze:** Dies ist ein Test des echten Core-Assets und des echten Bundles
  mit flachen UI-Testdoubles, kein vollständiger Firefox-Lauf unter WordPress
  6.8. Der fehlende Export ist damit reproduziert; PHP 8.2 wurde nicht getestet.
- **Notwendig:** Import/Bundles kompatibel machen und den FAQ-Editor auf der
  vollständigen Mindestkombination erneut testen.

### R3 – P2: `hasSync()` übernimmt den Cachewert einer anderen Site

- **Kombination:** Multisite, `switch_to_blog()` innerhalb desselben Requests;
  WP 7.1.3 / PHP 8.5.12.
- **Reproduktion:** `vendor/bin/phpunit --filter RolloutMultisiteCacheTest`.
  Derselbe Post-Type hat auf einer Site importierte Einträge und auf der anderen
  ausschließlich lokale Einträge. `hasSync()` vor und nach dem Site-Wechsel
  ausführen, auch in umgekehrter Reihenfolge.
- **Erwartet:** Das Ergebnis entspricht jeweils der aktuellen Site.
- **Tatsächlich:** dev liefert nach dem Wechsel den zuerst gespeicherten Wert;
  beide Richtungen scheitern. Dasselbe Testpaar besteht mit main.
- **Code:** `includes/Common/Tools.php:443–447,465`; der statische Cache ist nur
  nach Post-Type indiziert. Betroffen sind Aufrufer, die in einem Request mehrere
  Sites betrachten; ein isolierter normaler Seitenaufruf beweist diesen Fehler
  nicht.
- **Notwendig:** Site-Kontext im Cache berücksichtigen und beide Wechselrichtungen
  weiter als Regressionstest behalten.

## Sync-Befunde und Baseline-Abgrenzung

### B1 – P1, bereits auf main: Sync ohne Benutzer weist Taxonomien nicht zu

`RolloutCronSyncTest` führt FAQ-/Glossary-Insert und -Update mit Benutzer-ID 0 aus.
Neue Einträge haben anschließend keine Kategorie/Tags; bestehende behalten ihre
alten Beziehungen statt der aktuellen. Alle vier Fälle scheitern auf dev **und**
main. Der Test ruft den Sync-Service im Cron-typischen Benutzerkontext auf;
ein echter zeitgesteuerter Betriebssystem-Cron wurde nicht eingerichtet.

Das erwartete Verhalten ist ein vollständiger Sync einschließlich Beziehungen.
Die Übergabe über `tax_input` bei `wp_insert_post()`/`wp_update_post()` unterliegt
den WordPress-Zuweisungsrechten. Der abgeschlossene Datenmigrationsstatus behebt
diesen Laufzeitfehler nicht. Er ist ein bestehendes Betriebsrisiko, keine durch
diesen Branch neu eingeführte Regression. Unter dem vereinbarten Kriterium
„funktionierender unbeaufsichtigter Sync“ bleibt die Abnahme dennoch rot.
Code: `includes/Common/Sync/SynchronizedEntryStore.php:247–262,282–301`;
main: `includes/Common/API/SyncAPI.php:483–522`.

### B2 – P1, bereits auf main: Alte Consumer verwechseln fremde Term-IDs

`RolloutWireCompatibilityTest` erfasst echte REST-Antworten eines Producer-Blogs
einschließlich Embeds und Taxonomie-Endpunkten. Ein getrenntes Consumer-Blog hat
absichtlich andere IDs; lokale Distraktoren und explizite Assertions sichern
diese Abweichung ab. Jeweils FAQ und Glossary werden geprüft, einschließlich
Wiederholung ohne Duplikate und geändertem Quellzustand mit umbenannten
Taxonomien und erhöhtem `remoteChanged`.

| Producer → Consumer | Ergebnis |
| --- | --- |
| dev → dev | **Bestanden** |
| main → dev | **Bestanden** |
| dev → main | **Fehlgeschlagen**: falsche bzw. fehlende Taxonomien |
| main → main | **Fehlgeschlagen**, derselbe Fehler bereits in der Baseline |

main interpretiert fremde numerische IDs über seine lokale Term-Tabelle. Die
zusätzlichen Namensfelder des dev-Producers beseitigen diesen Fehler im alten
Consumer nicht. Ein gestaffelter Rollout mit alten Empfängern bleibt in diesem
Szenario funktional unzuverlässig; die neuen Empfänger bestehen den Vergleich.
Die HTTP-Transportschicht wird hier deterministisch mit den erfassten Antworten
bedient; externe Producer wurden nicht kontaktiert.
Code des alten Consumers: main `includes/Common/API/SyncAPI.php:571`;
Regressionstest: `tests/phpunit/RolloutWireCompatibilityTest.php`.

### B3 – P2, bereits im main-Code: Standard-Placeholder-Archiv leitet nicht um

Von 20 echten Loopback-HTTP-Fällen bestehen 19. Mit gespeichertem
`redirect_archivpage_uri_placeholder=10` und leerem `custom_placeholder_slug`
liefert `/placeholder/` HTTP 200 ohne Location statt HTTP 301 zur Zielseite.
Mit einem individuellen Placeholder-Slug funktioniert die Weiterleitung.
Reproduktion: `wp eval-file tests/rollout-browser/check-http.php
--path=/private/tmp/rrze-answers-rollout-browser --skip-themes --quiet`.

`includes/Common/CPT/CPTPlaceholder.php:59,79` verwendet in den beiden
Redirect-Prüfungen `glossary` beziehungsweise `faq` als Default statt
`placeholder`. Diese Datei ist bytegleich mit main; die Baseline-Zuordnung ist
hier durch Codevergleich belegt, nicht durch einen zweiten HTTP-Serverlauf auf
main. Erwartet ist die konfigurierte Weiterleitung auch ohne individuellen Slug.

## Gezielte Verhaltensänderungen

- **Gruppen-/Buchstabenanker:** main verwendet `#letter-A` und
  `#ID-<term_id>`, dev eindeutige Präfixe wie `#rrze-answers-index-1-A` und
  `#rrze-answers-index-1-term-42`. Interne Navigation funktioniert mit den neuen
  Zielen; vorhandene externe Links auf alte Gruppenanker erhalten keinen Alias.
  Dies ist ein P2-Kompatibilitätsbefund für bestehende Deep Links, kein Fehler
  der getesteten neuen Navigation. Individuelle `anchorfield`-Werte sowie
  `ID-<post_id>`/`innerID-<post_id>` bleiben bestehen. Code:
  `includes/Common/Content/QuestionListRenderer.php:71,115,184`; main:
  `ShortcodeFAQ.php:579–602,657` und `ShortcodeGlossary.php:407–430,485`.
- **HTML-Bereinigung:** Placeholder-HTML wird nach dem Dekodieren erneut mit
  `wp_kses_post()` bereinigt. Synonym-Langformen werden als Text escaped.
  Eventhandler entfallen; früher als HTML interpretierte Synonyme können anders
  erscheinen. Sicheres Placeholder-HTML bleibt erhalten. Code:
  `includes/Common/Content/PlaceholderRenderer.php:28` und
  `includes/Common/Shortcode/ShortcodeSynonym.php:88`. Die bestehenden Tests
  bestätigen diese beabsichtigte Einschränkung.
- **Sichtbarkeit:** Einbettungen verlangen passenden Post-Type, `publish` und
  gegebenenfalls die erfolgreiche Passwortfreigabe. Referenzen auf nicht mehr
  veröffentlichte/fehlende Placeholder verwenden keinen veralteten
  `title`-Snapshot mehr; wirklich alte Marker ohne ID behalten den bereinigten
  Fallback. Dadurch entfallende Ausgabe ist eine Sicherheitsänderung, kein
  nachgewiesener Datenverlust. Code: `includes/Common/Content/EntryQuery.php:14`
  und `PlaceholderRenderer.php:49`. Bestandstests für geschützte Inhalte und
  HTML-Bereinigung sind grün.

## Testmatrix

„Bestanden“ gilt nur für den jeweils beschriebenen Umfang, nicht pauschal für
alle Theme-, Laufzeit- und Benutzerkombinationen.

| Prüfung | Umgebung/Umfang | Ergebnis |
| --- | --- | --- |
| Bestehende JavaScript-Tests | 33 Tests | **Bestanden** |
| Neue Editor-/Rollback-Tests | 16 Tests; 7 grün, 9 rot | **Fehlgeschlagen**, R1/R2 |
| Gesamter JavaScript-Lauf | 49 Tests; 40 grün, 9 rot | **Fehlgeschlagen**, ausschließlich neue Akzeptanztests |
| TypeScript | `npm run typecheck` | **Bestanden** |
| PHPStan | initial Standardlauf; Abschluss mit `--debug --memory-limit=2G` | **Bestanden**, keine Fehler; serieller Debug-Lauf benötigte mehr als 1 GiB |
| Build-Manifest | `npm run check:build`, alle fünf Blöcke | **Bestanden** |
| Frischer Build gegen ausgelieferte Assets | temporäre Kopie, 31 Dateien bytegleich | **Bestanden** |
| Bestehende PHP-Tests | 133 Tests im ersten Gesamtlauf | **Bestanden** |
| Abschließende gesamte PHPUnit-Suite | WP 7.1.3 / PHP 8.5.12; 146 Tests, 726 Assertions | **140 bestanden, 6 fehlgeschlagen**: B1 × 4, R3 × 2; keine Errors/Skips |
| Dieselben neuen PHP-Proben auf main | 13 Tests, 145 Assertions | **7 bestanden, 6 fehlgeschlagen**: B1 × 4, B2 × 2 |
| Standard-Upgrade v1.4.6 → dev | WP 7.1.3 / PHP 8.5.12 | **Bestanden**, bisherige Abdeckung erhalten |
| main → dev: Start/Lesen | zwei Sites, Daten/Metadaten/Beziehungen/Optionen/Cron | **Bestanden** |
| Alte Blöcke speichern/erneut rendern | Upgrade-Harness, beide WP-Versionen / PHP 8.5.12 | **Bestanden** |
| Rollback vor Editor-Save | beide WP-Versionen / PHP 8.5.12 | **Bestanden** |
| Rollback nach Editor-Save | beide WP-Versionen / PHP 8.5.12 | **Fehlgeschlagen**, R1 |
| Rollback nach Save + Sync | beide WP-Versionen / PHP 8.5.12 | **Fehlgeschlagen**, R1 |
| Unabhängiger Sync-only-Rollback | frische Ausgangslage, beide WP-Versionen / PHP 8.5.12 | **Bestanden** |
| Fünf ausgelieferte Editor-Bundles | Firefox, WP 7.1.3; Legacy-Blöcke unter beiden Themes | **Bestanden** |
| Fünf neue Blöcke einfügen, Auswahl ändern, speichern, öffnen | Firefox, WP 7.1.3, FAU-Elemental | **Bestanden** |
| Echtes Inline-Synonym | Firefox: Einfügen, Bearbeiten, Speichern/Öffnen, Format entfernen | **Bestanden** |
| Echter Inline-Placeholder | Firefox: Einfügen, Speichern/Öffnen; bestehendes Format erhalten | **Bestanden** für diese Schritte; erneutes Bearbeiten/Entfernen nicht vollständig geprüft |
| Erster/wiederholter Sync, Fehler/Leerantworten, wiederherstellbare Löschungen | bestehende PHP-Suite und Upgrade-Harness | **Bestanden** in den vorhandenen Szenarien |
| Cron-typischer Sync ohne Benutzer | FAQ/Glossary, Insert und Update, main/dev | **Fehlgeschlagen**, B1 bereits auf main |
| Anonymer REST-Export an/aus | vier FAQ-/Glossary-Fälle einschließlich Authentifizierungsfilter, main und dev | **Bestanden** |
| Site-Wechsel im selben Request | beide Reihenfolgen; main grün, dev rot | **Fehlgeschlagen**, R3 |
| Producer-/Consumer-Matrix | vier Kombinationen mit unterschiedlichen Term-IDs | **Teilweise fehlgeschlagen**, B2 |
| Änderung eines vorhandenen individuellen Slugs | begrenzter direkter Option-Update-Test auf main und dev | **Bestanden**; ursprünglicher WP-CLI-Prüfaufbau-Hänger kein bestätigter Produktfehler |
| Archive, Weiterleitungen, individuelle Slugs, fehlende Ziele/Einträge | echte lokale HTTP-Antworten, vier Inhaltstypen × fünf Fälle | **19 bestanden, 1 fehlgeschlagen**, B3 |
| Inline-HTML-Vertrag main/dev | echter Gutenberg-Rich-Text-Parser/-Serializer | **Bestanden** |
| Mindestkombination WP 6.8 / PHP 8.2 | PHP 8.2 nicht installiert | **Nicht geprüft** |
| Kompletter Editor/Theme-Lauf auf WP 6.8 | nur Asset-Vertrag und PHP-Upgrade auf PHP 8.5.12 geprüft | **Nicht geprüft** |

Die roten Akzeptanztests wurden absichtlich nicht durch geänderte Erwartungen
oder Produktkorrekturen grün gemacht. Ihr Exitcode bleibt ungleich null.

Nicht separat bewiesen sind reine Term-Umbenennungen bei unverändertem
`remoteChanged`, eine automatische Reparatur bereits falsch importierter
Taxonomien, ein vollständiger Firefox-Lauf auf WordPress 6.8 und die Kombination
dieses Cores mit PHP 8.2. Die erweiterten Wire-Prüfungen arbeiten mit
synthetischen REST-Antworten; reale externe CMS-Instanzen wurden nicht verändert
oder als Testsender verwendet.

Der Upgrade-Harness erfasst Core-Deprecations gesondert und schließt wie bisher
`E_DEPRECATED` aus seinem Fail-fast-Verhalten aus. Ein grüner Lauf ist deshalb
kein Nachweis völliger Deprecation-Freiheit des Plugins. Die alte
Translations-Laden-vor-init-Diagnose wird beim main-Vergleich gezielt ausgeblendet,
damit sie die eigentlichen Kompatibilitätsprüfungen nicht verhindert.

## Firefox- und Frontend-Prüfung

Die separate Browserinstallation lief auf `127.0.0.1:8876` mit Präfix
`rrze_rollout_browser_`. Die automatisierten DB-Suites liefen sequenziell in
anderen Präfixen. Es gab keine konkurrierenden Änderungen desselben Bestands.
Testserver und eigene Browser-Testtabs wurden anschließend geschlossen.
Ein abschließendes Lesen der gespeicherten Testseite bestätigt die Array-IDs
der Blöcke sowie `data-placeholder-id="9"` am tatsächlich per Toolbar
eingefügten Inline-Placeholder.

| Kombination | Geprüftes Verhalten | Ergebnis |
| --- | --- | --- |
| FAU-Einrichtungen 2.6.38 + rrze-elements 1.31.7 | verfügbarer Core-Akkordeon-Pfad: geschlossen, Öffnen, Suche, `load_open`, Eintragsanker | **Bestanden**; Core-Pfad zunächst im In-App-Browser, anschließende UI-Arbeit ausschließlich Firefox |
| FAU-Einrichtungen + erzwungener nativer Renderer | Firefox: geschlossen, Klick/Enter, No-match-Suche, `load_open`, Eintragsanker | **Bestanden** |
| FAU-Elemental 1.0.21 + rrze-elements-blocks 1.3.3 | Firefox: geschlossen, Klick/Space, Suche, `load_open`, Eintragsanker, A–Z und Kategorie-Tabs | **Bestanden** |
| Elements-Tastatur | Enter auf fokussiertem Akkordeon-Button | **Fehlgeschlagen**; Space funktioniert |

Beim Elements-Renderer verarbeitet das installierte Dependency-Skript
`rrze-elements-blocks/assets/js/accordion/rrze-accordion.js` im Keydown-Pfad nur
die Leertaste (`keyCode === 32`). Dies ist ein bestätigter Integrationsmangel,
aber in dieser Prüfung kein als neu bewiesener Fehler des Answers-Produktcodes.
Die automatische Renderer-Auswahl macht die konkrete Dependency für das
ausgelieferte Verhalten relevant. Vor Freigabe muss die gewünschte
Tastaturbedienung für diese Kombination funktionieren.

`rrze-elements-blocks` 1.3.3 verlangt WordPress >= 7.0. Dieser Lauf ist daher kein
Nachweis für dieselbe Plugin-Kombination unter WordPress 6.8.

## Voraussetzungen für eine spätere Freigabe

1. R1 und R2 korrigieren und die aktuell roten Editor-/Rollback-Akzeptanztests
   erneut bestehen lassen; R3 mit Site-spezifischem Cache absichern.
2. Unbeaufsichtigten Sync und alte Consumer gemäß B1/B2 betriebsfähig machen oder
   eine ausdrücklich angepasste Rollout-Voraussetzung festlegen und testen. Der
   aktuelle Auftrag verlangt weiterhin einen sicheren reinen Code-Rollback.
3. Die reale Mindestkombination WordPress 6.8 / PHP 8.2 einschließlich FAQ-Editor
   nachweisen. Die unterstützte Theme-/Elements-Kombination dafür konkret
   festhalten; die getestete Elements-Blocks-Version reicht bis 6.8 nicht zurück.
4. Elements-Tastaturbedienung, bestehende Gruppen-Deep-Links und B3 abarbeiten;
   die absichtlichen Bereinigungs-/Sichtbarkeitsänderungen bei der Inhaltsabnahme
   berücksichtigen.

Diese Prüfung implementiert die Diagnose und liefert die Belege. Korrekturen
und Deployment bleiben außerhalb dieses Auftrags.

## Reproduktion und Belege

Die neuen Funktionen der Prüfhilfen sind mit PHPDoc/JSDoc dokumentiert.
Anleitungen stehen in `tests/fixtures/v1.4.6/README.md`,
`tests/rollout-editor/README.md`, `tests/rollout-sync/README.md` und
`tests/rollout-browser/README.md`.

| Beleg | Lokaler Pfad |
| --- | --- |
| main-Upgrade und Rollbacks, WP 7.1.3 | `/var/folders/1q/h1svxyqj4zzcnsh8kn4d0jsr0000gp/T/rrze-answers-upgrade-88e6959e1787/results.json` |
| Konkrete verbreiterte Ausgaben | gleicher Ordner, `rollback-after-save.json` |
| WP-6.8- und unabhängige Sync-only-Matrix | `/var/folders/1q/h1svxyqj4zzcnsh8kn4d0jsr0000gp/T/rrze-answers-upgrade-matrix-816013802efd/results.json` |
| Standard v1.4.6 | `/var/folders/1q/h1svxyqj4zzcnsh8kn4d0jsr0000gp/T/rrze-answers-upgrade-acbccc14f757/results.json` |
| Buildvergleich | `/var/folders/1q/h1svxyqj4zzcnsh8kn4d0jsr0000gp/T/rrze-answers-build-check-hif1JF/comparison.json` |
| Gesamter JS-Lauf | `/private/tmp/rrze-answers-rollout-jest.log` |
| Abschließender PHPStan-Lauf | `/private/tmp/rrze-answers-rollout-phpstan.log` |
| Archiv-/URL-HTTP-Matrix | `/private/tmp/rrze-answers-rollout-http-results.json` |
| Finale REST-/Sync-/Multisite-Baseline-Matrix | `/private/tmp/rrze-answers-rollout-baseline.PFw8s7/final-results.json` |
| Abschließende gesamte PHPUnit-Suite | gleicher Ordner, `full-candidate-suite.log` |
| Firefox nach Speichern/Öffnen | `/private/tmp/rrze-answers-rollout-firefox-saved-blocks.png` |

Die Belege enthalten synthetische Testdaten und liegen in temporären lokalen
Verzeichnissen. Vor deren Bereinigung bei Bedarf archivieren. Erfolgreiche Tests
auf PHP 8.5.12 ersetzen den fehlenden Test auf PHP 8.2 ausdrücklich nicht.
