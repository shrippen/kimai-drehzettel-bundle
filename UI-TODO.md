# UI-TODO (kimai-plugin-ui)

Umsetzung von [kimai-plugin-ui GUIDELINES.md](https://github.com/shrippen/kimai-plugin-ui/blob/main/GUIDELINES.md) im Drehzettel.
`[x]` erledigt, `[ ]` offen mit Grund.

## Alle Seiten
- [x] Kit 0.1.0 mit `bin/sync.sh` übernommen, eigener Commit
- [x] Kit 0.2.0 übernommen (eigener Commit), Ersatzlösungen durch Kit-Bausteine ersetzt (siehe unten)
- [x] Modal-Formulare antworten nach GUIDELINES 3.6 (`KpuFormSuccessTrait`): neu/löschen → 201 + `x-modal-redirect`, bearbeiten/Mail → leere 200 + `data-form-event: kpu.reload`; keine eigenen `kimai.drehzettel*`-Listener mehr
- [x] Plural: keine `%count%`-Intervalle im Plugin (`catering_days` ist ohne Plural und passt für 0)
- [x] Sofort-Aktionen (`data-kpu-post`): keine im Plugin (alle „…“-Einträge öffnen Seiten oder Kimai-Modale)
- [x] Titel je Seite „Drehzettel · …“ (`PageSetup`), `setActionName()`, `setHelp()` auf README
- [x] Keine `<h2>` im Inhalt, Kontext über `kit.context_line`
- [x] Seitenaktionen über `PageActionsEvent`-Subscriber (Icon-Knöpfe + „…“)
- [x] Kein `<style>` im Seiteninhalt (Tagesraster/Stufenleiste: wenige Regeln im `stylesheets`-Block, nur `var(--tblr-…)`), keine Hex-/rgb-Farben, kein `btn-xs`
- [x] Kein Inline-JS-IIFE, Skripte im `javascripts`-Block auf `kimai.initialized`
- [x] Formate über Kimai-Filter (`date_short`, `duration`, `money(currency)`, `format_date`)
- [x] Glossar: „Tätigkeit“ → „Funktion“/„Crew role“, „Nutzer“ → „Benutzer“, „Gage (€)“ → „Gage“/„Pay“, „Timesheet“-Knopf → „Wochen-PDF“/„Week PDF“
- [x] de/en mit gleichem Key-Bestand, keine deutschen Texte im Template/JS
- [x] 390 px ohne waagrechtes Scrollen, Dunkelmodus geprüft

## Übersicht `/drehzettel/`
- [x] Tabelle über `macros/datatables.html.twig`, Spaltenklassen, Zeilenaktionen im „…“-Menü
- [x] Aktionen: Neues Engagement (Modal), Regelwerke, Unterschrift
- [x] Datum `date_short`, Leerzustand `kit.empty_state` mit nächstem Schritt; „Neues Engagement“ öffnet dort das Kimai-Modal (`modal-ajax-form`, Kit 0.2)
- [x] Engagement löschen im „…“ mit Kimai-Löschmodal

## Woche `/drehzettel/{id}/week/{y}/{w}`
- [x] Titel „Drehzettel · KW 21“, Kontextzeile Projekt · Funktion · Benutzer · Zeitraum
- [x] Zeitraum über `kit.period_nav` (nur Woche; Monat nur als Monats-PDF, keine eigene Monatsansicht)
- [x] Aktionen: Speichern, Wochen-PDF, Monats-PDF, Mailen (Modal), „…“: Engagement bearbeiten, Regeln, Löschen
- [x] Kennzahlen über `kit.kpi_bar` (Arbeitszeit, Zuschläge, Nacht, Gage hervorgehoben); Zuschläge je Stufe (+25 %, +50 %, +100 %) als `details` statt im Hinweistext
- [x] Tagesraster als Tabler-Tabelle in `.kpu-table-wrap`, ohne Hex-Farben
- [x] Tag ohne Eintrag `kit.status_badge('open')`; Verstöße (Tageshöchstzeit, Ruhezeit) und Unterstunden als `kit.status_badge('warning', Grund)` mit Kurzgrund daneben, Wochenliste als Kimai-Hinweis; Spalte Unterstunden nur noch Zahl
- [x] Live-Vorschau zeigt Fehler, ersetzt nur berechnete Zellen (Eingaben behalten den Fokus)
- [x] Mail als FormType im Kimai-Modal; Erfolg als Ergebnis-Hinweis „Wochen-PDF an … gesendet“ (`kpu_result`) statt Toast

## Engagement anlegen/bearbeiten
- [x] `EngagementType` (UserType, ProjectType, DatePickerType, MoneyType mit Kundenwährung) im Kimai-Modal
- [x] Löschen: Bestätigungsseite/-modal `default/_form_delete*.html.twig`

## Regelwerke (Liste, neu, bearbeiten, Engagement-Regeln)
- [x] Liste als Tabelle mit „…“-Menü, Löschen mit Kimai-Modal statt `confirm()`
- [x] Editor als Seite mit `RulesetType` und Kimai-`_form`-Karte
- [x] Fünf deutsche Kartenbeschreibungen und JS-Validierungstexte → Keys bzw. Symfony-Constraint (`validators`)
- [x] Stufenleiste mit Tabler-Farbklassen

## Unterschrift
- [x] `SignatureType` (FileType + File-Constraint), Kimai-`_form`-Karte
- [x] Entfernen mit Kimai-Löschmodal

## Zeiteintrags-Formular (sitewide) und Arbeitszeiten-Seite
- [x] ThemeSubscriber-JS: Texte aus Übersetzung über `data-*`, Start auf `kimai.initialized`, Hinweis als Tabler-Alert
- [x] CSS nur mit `var(--tblr-…)`

## PDF
- [x] Wochentage/Monate über `IntlDateFormatter`, Geld mit Kundenwährung (Layout bleibt)

## Offen / bewusste Abweichungen
- [ ] Eigene Monatsansicht: nicht gebaut, Monat nur als Monats-PDF (Segment zeigt deshalb nur Woche)
- [ ] Gage beim Anlegen ohne Währungssymbol: Projekt und damit Kundenwährung stehen erst nach der Auswahl fest
- [ ] Unterschrift-Vorschau mit `bg-white`: schwarze Tinte bliebe im Dunkelmodus unsichtbar
- [ ] PDF-Dauern bleiben „08:45 h“ (Druckformat für Produktionsbüros)
