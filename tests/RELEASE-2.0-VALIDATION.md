# Lokale Validierung der 2.0-Korrekturen – 27.10.2026

Dieser Nachtrag bewertet die Implementierung nach dem historischen
[Rollout-Audit](ROLLOUT-REVIEW-2026-10-27.md). Dessen Befunde und ursprüngliche
rote Tests bleiben als Ausgangsnachweis unverändert erhalten.

## Vereinbarter Rahmen

- Neue Major-Version **2.0.0**, WordPress **ab 7.0**.
- Vorwärts-Upgrade vorhandener Inhalte bleibt unterstützt. Ein Austausch nur
  der Plugin-Dateien zurück auf 1.x ist ausdrücklich nicht unterstützt.
- Die CMS-Migration wurde nicht wiederholt; normale CMS-Inhalte und Optionen
  wurden nicht verändert. Alle Datenbankprüfungen verwenden `rrze_answers_tests`.
- PHP **8.5.12** ist die tatsächlich geprüfte Laufzeit. PHP **8.2 wurde auf
  ausdrücklichen Wunsch nicht getestet**; die deklarierte Mindestversion 8.2
  wurde nicht angehoben und ist kein Nachweis einer getesteten Kombination.
- Firefox für Browserprüfungen. Kein Deployment und kein Push.

## Implementierung und getrennte Commits

| Commit | Änderung | Nachweis |
| --- | --- | --- |
| `def74e9` | Audit und ursprüngliche Regressionstests sichern | Historischer Ausgangszustand |
| `8cad3c2` | Version 2.0, Mindest-WordPress 7.0 und Upgrade-Vertrag | Echte 7.0-SSR-Exports; alte Attribute speichern/öffnen |
| `963bfef` | Cron-Sync weist Terms explizit zu und prüft die gespeicherten Beziehungen | Benutzer 0, Insert/Update, Wiederholung, Leerlisten und echte SQL-Fehler |
| `20daa9d` | `hasSync()` berücksichtigt Site und WordPress-Cachegeneration | Site-Wechsel in beide Richtungen sowie Änderungen innerhalb eines Requests |
| `5630804` | Placeholder-Archiv nutzt seinen eigenen Standard-Slug | 20 echte HTTP-Fälle je WordPress-Version |
| `063928b` | Enter-Adapter für Answers-eigene Elements-Akkordeons | Reales installiertes Dependency-Skript, DOM-Tests und Firefox |
| `b226f32` | Alte Buchstaben-/Gruppenlinks, eindeutige Ziele und Erhaltung vorhandener Hashes bei `load_open` | Mehrere Listen, echte ID-Kollisionen, Tabs und native Integration |

Die Elements-Dependency wurde nicht geändert. Der Adapter nutzt deren
Aktivierungspfad und beschränkt sich auf Answers-eigene Controls. Die
Tab-Navigation aktiviert ebenfalls die vorhandenen Buttons der Dependency.
Neue und angepasste Funktionen sind mit PHPDoc/JSDoc dokumentiert.

Beim Sync werden neue Beiträge vor der Term-Zuweisung journalisiert. Nach dem
Schreiben werden die tatsächlich gespeicherten Term-IDs verglichen, weil nicht
jeder SQL-Fehler als `WP_Error` zurückkommt. Dieselbe Prüfung gilt für die
Kompensation. Fehler in der Wiederherstellung werden ausdrücklich gemeldet.

## Automatisierte Matrix

| Prüfung | WordPress 7.0 / PHP 8.5.12 | WordPress 7.1.3 / PHP 8.5.12 |
| --- | --- | --- |
| Vollständige PHPUnit-Suite | **Bestanden**, 165 Tests / 1.087 Assertions | **Bestanden**, 165 Tests / 1.087 Assertions |
| `v1.4.6` → 2.0 auf zwei Sites | **Bestanden** | **Bestanden** |
| Festgehaltenes `main` → 2.0 auf zwei Sites | **Bestanden** | **Bestanden** |
| Erstmaliger und wiederholter Sync nach Speichern | **Bestanden** | **Bestanden** |
| 2.0-Producer → 2.0-Consumer, unterschiedliche Term-IDs | **Bestanden**, Teil der Suite | **Bestanden**, Teil der Suite |
| Aufgezeichnete echte `main`-REST-Antworten → 2.0-Consumer | **Bestanden**, 2 Tests / 100 Assertions | **Bestanden**, 2 Tests / 100 Assertions |
| Anonyme REST-Aufrufe mit Export an/aus | **Bestanden**, Teil der Suite | **Bestanden**, Teil der Suite |
| Archive, individuelle Slugs und Weiterleitungen | **Bestanden**, 20/20 HTTP-Fälle | **Bestanden**, 20/20 HTTP-Fälle |

Weitere Prüfungen:

- **71/71 JavaScript-Tests bestanden**, einschließlich der unveränderten
  offiziellen `server-side-render.js` aus WordPress 7.0 und aller fünf
  ausgelieferten Block-Bundles.
- **PHPStan bestanden**, seriell mit `--debug --memory-limit=2G`.
- **TypeScript-Prüfung bestanden**; Build und Blockmanifest-Validierung bestanden.
- **Frischer Build in separater Kopie:** 33 Artefakte, keine Unterschiede.
- Diff-Whitespaceprüfung und Syntaxprüfungen der ergänzten Prüfhilfen bestanden.

Die Upgrade-Läufe verwenden die unveränderten Referenzen
`fe1c7a8f8a913778feb3ef4960880ffe6c0a2170` (`v1.4.6`) und
`3b458c812ba61c1f9b8beac22022cfd7b159ba58` (`main`). Geprüft werden Start,
Bestandsdaten, REST-Speichern, anschließendes Rendering und Sync. Die
Upgrade-Worker isolieren jetzt ihre MU-Plugins: Ein erster Wiederholungslauf
hatte wegen eines Offline-Hooks der Browser-Fixture angehalten, nicht wegen
eines Produktfehlers. Nach der Isolation bestanden alle vier Läufe.

## Erneute Firefox-Prüfungen

| Kombination | Tatsächlich erneut geprüft | Ergebnis |
| --- | --- | --- |
| WP 7.0, FAU-Elemental 1.0.21, rrze-elements-blocks 1.3.3 | Alle fünf alten Blöcke öffnen; Suchoption ändern; speichern; als Array-/Boolean-Attribute nachweisen; erneut öffnen; feste Widget-Auswahl speichern/öffnen | **Bestanden** |
| Dieselbe Kombination | Geschlossen laden, Enter öffnen/schließen, Space, alter Gruppenlink öffnet den verborgenen zweiten Tab | **Bestanden** |
| Dieselbe Kombination | Echte vorhandene Eintrags-ID hat Vorrang vor gleichnamigem Gruppenalias | **Bestanden** |
| WP 7.0, FAU-Einrichtungen 2.6.38, rrze-elements 1.31.7 | Automatischer Core-Akkordeon-Pfad, geschlossen laden, Enter, Suche ohne Treffer | **Bestanden** |
| Dieselbe Kombination, nativer Fallback | Alter Buchstabenlink zusammen mit `load_open`; Hash bleibt erhalten; anschließender Eintragsklick funktioniert | **Bestanden** |
| WP 7.1.3, FAU-Elemental 1.0.21, rrze-elements-blocks 1.3.3 | Geschlossen laden, Enter öffnen/schließen, Space, Suche ohne Treffer, `load_open` | **Bestanden** |

Die vorhandenen Synonym-/Placeholder-Inlineformate bleiben nach dem echten
7.0-Editor-Speichern erhalten. Die vollständige manuelle Neu-Einfügung aller
fünf Blöcke und beider Inline-Toolbarformate wurde bereits im ursprünglichen
7.1.3-Audit durchgeführt; sie wurde in diesem Nachtrag nicht vollständig
wiederholt. Die betreffenden Block-Bundles wurden durch diese Korrekturen nicht
geändert. Parser-/Serialisierungs- und Vorschautests laufen weiterhin für sie.

Die neue, leere 7.0-Fixture benötigte vor dem ersten Start von FAU-Einrichtungen
ein initialisiertes `Social Media Menu`; andernfalls scheitert diese installierte
Theme-Version in ihrer eigenen Menüinitialisierung (`ksort(null)`). Das Menü
wurde nur in der synthetischen Fixture angelegt. Das Theme wurde nicht repariert;
die Browseraussage gilt für den bereits initialisierten Zustand.

## Grenzen und Bewertung

- **R1:** Code-only-Rollback ist aus dem 2.0-Vertrag entfernt. Für eine Rückkehr
  zu 1.x wären passende alte Dateien und ein Datenbankbackup vor dem Upgrade nötig.
- **R2:** WordPress 6.8 ist außerhalb des Vertrags. Der FAQ-Export wurde auf
  dem echten WordPress 7.0 geprüft; hierfür war kein Produkt-Patch nötig.
- **R3/B1/B3:** Multisite-Cache, anonymer Cron-Sync und Placeholder-Redirect sind
  korrigiert und durch Regressionstests abgesichert.
- **B2:** Alte Consumer bleiben fehlerhaft. Deshalb müssen alle Consumer vor
  Wiederaufnahme des Sync auf 2.x stehen. Ein Major-Release repariert keine noch
  auf 1.x laufende Gegenseite. Eine Producer-Payload-Sonderlösung wurde nicht eingebaut.
- Alte Gruppen-/Buchstabenfragmente benötigen JavaScript; aktuelle eindeutige
  IDs funktionieren weiterhin nativ. Bei Kollisionen haben echte IDs Vorrang,
  bei wiederholten Listen die erste passende Stelle im Dokument.
- HTML-Bereinigung und eingeschränkte Sichtbarkeit geschützter Inhalte bleiben
  die beabsichtigten Änderungen aus dem Audit; ihre Regressionstests sind grün.
- **Nicht geprüft:** PHP 8.2, ältere WordPress-Versionen, Code-only-Downgrade,
  Produktionsdaten und Produktionsinfrastruktur. Term-only-Änderungen ohne
  fortgeschrittenes `remoteChanged` bleiben außerhalb der bisherigen Sync-Probe.

Für den vereinbarten lokalen 2.0-Umfang sind die implementierten Fehlerkorrekturen
bestanden. Eine Freigabe setzt die dokumentierte Consumer-Upgrade-Reihenfolge
voraus. Dies ist keine Bestätigung einer PHP-8.2-Prüfung oder eines Deployments.

## Lokale Belege und Wiederholung

Die Logs enthalten ausschließlich synthetische Daten und liegen temporär:

- `/private/tmp/rrze-answers-php70.log`
- `/private/tmp/rrze-answers-upgrade70-v146.log`, `rrze-answers-upgrade70-main.log`
- `/private/tmp/rrze-answers-upgrade71-v146.log`, `rrze-answers-upgrade71-main.log`
- `/private/tmp/rrze-answers-http70.json`
- `/private/tmp/rrze-answers-wire70-main-to-2.log`, `rrze-answers-wire71-main-to-2.log`
- `/private/tmp/rrze-answers-2-wp70-editor.png`
- Buildvergleich: `/var/folders/1q/h1svxyqj4zzcnsh8kn4d0jsr0000gp/T/rrze-answers-build-check-wYCLv3/comparison.json`

Reproduktionsbefehle und Schutzprüfungen stehen in [README.md](README.md),
[rollout-editor](rollout-editor/README.md), [rollout-browser](rollout-browser/README.md)
und [rollout-sync](rollout-sync/README.md). Das historische Audit und dessen
Baseline-Matrix wurden nicht nachträglich auf grün umgeschrieben.

## Nachprüfung: große Akkordeon-Listen

Nach der ersten Validierung wurde ein zusätzlicher Fehler bei sehr vielen
Einträgen reproduziert. Ohne Kategorie oder Gruppierung entsteht eine große
zusammenhängende Liste. Deren regulärer Ausdruck überschritt das normale
`pcre.backtrack_limit=1000000`; die internen Blockmarkierungen blieben im Ergebnis
stehen und die Akkordeon-Blöcke wurden nicht gerendert. Kleinere Gruppen konnten
denselben Datenumfang erfolgreich ausgeben. Kategorien oder „Group by“ sind
weiterhin keine Voraussetzung für Akkordeons.

Die Sammlung liest ihre festen Anfangs-/Endmarkierungen jetzt nacheinander aus,
ohne einen regulären Ausdruck über den gesamten Inhalt laufen zu lassen. Dabei
bleiben Reihenfolge, Gruppengrenzen, Schema-Ausgabe und `load_open` erhalten.
Unvollständige abschließende Markierungen werden samt Inhalt beibehalten.
PHP-Limits, gespeicherte Daten und ausgelieferte JavaScript-Bundles werden durch
diese Korrektur nicht verändert.

- Vor der Korrektur: vier neue Mengenfälle fehlgeschlagen (Elements/Core,
  jeweils 1.000 Einträge oder eine einzelne Antwort größer als 1 MB).
- Nach der Korrektur: **9 Renderer-Tests / 76 Assertions bestanden**.
- Vollständige PHP-Suite erneut bestanden: **170 Tests / 1.131 Assertions**
  jeweils unter **WordPress 7.0 und 7.1.3 mit PHP 8.5.12**.
- Reale installierte Backends: 1.000 Einträge ohne Gruppierung erfolgreich unter
  WP 7.1.3 mit Elements sowie WP 7.0 mit Core; keine verbliebenen internen
  Markierungen und kein PCRE-Fehler. Die Prüfung verwendet synthetischen Inhalt
  im Arbeitsspeicher der isolierten Installationen.
- PHPStan, PHP-Syntax und Diff-Whitespaceprüfung bestanden.

Die große Liste wurde serverseitig erneut geprüft; eine zusätzliche visuelle
Firefox-Prüfung dieser Mengenfälle wurde nicht durchgeführt. Unabhängig davon
bleibt der zusätzlich festgestellte Anzeigeoptionsfehler offen: Gruppierte
Listen ignorieren ein aktiviertes `hide_accordion`. Er ist nicht die Ursache des
hier korrigierten Renderausfalls.
