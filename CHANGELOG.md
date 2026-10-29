# Changelog

Each release section is also its release note (GitHub and Gitea) and its entry under "Changes" on the
landing page.

## [0.3.0] — 2026-10-29

### English

A log of the mails per engagement, shooting days marked on Kimai's working times page, and the plugin runs on PHP 8.1 again.

#### New
- Mail log: the dialog "Mail template and automatic sending" lists the last 10 mails of the engagement, automatic and manual, with time, period, recipient and result (sent, failed, skipped). A retry after a failure counts on the same row; only administrators see the reason of a failure.
- Kimai's working times page (`/contract`) marks every shooting day the Drehzettel counts, also days whose entries came from the stopwatch or quick entry; travel days are not marked.
#### Improved
- Automatic mails that were sent or skipped are written to the Kimai log.
#### Fixed
- On PHP 8.1 the plugin failed to load ("Constant expression contains invalid operations").
#### Note
- New table for the mail log: after updating, run `bin/console kimai:reload -n` and `bin/console kimai:bundle:drehzettel:install`.
- The release zip is now attached to the release on GitHub.

All changes: https://github.com/shrippen/kimai-drehzettel-bundle/compare/v0.2.0...v0.3.0

### Deutsch

Ein Protokoll der Mails je Engagement, Drehtage auf Kimais Arbeitszeiten-Seite markiert, und das Plugin läuft wieder unter PHP 8.1.

#### Neu
- Mail-Protokoll: der Dialog „Mail-Vorlage und automatischer Versand“ zeigt die letzten 10 Mails des Engagements, automatische und manuelle, mit Zeit, Zeitraum, Empfänger und Ergebnis (gesendet, fehlgeschlagen, übersprungen). Ein neuer Versuch nach einem Fehler zählt in derselben Zeile; den Grund eines Fehlers sehen nur Administratoren.
- Kimais Arbeitszeiten-Seite (`/contract`) markiert jeden Drehtag, den der Drehzettel zählt, auch Tage, deren Einträge aus der Stoppuhr oder der Schnellerfassung kommen; Reisetage werden nicht markiert.
#### Verbessert
- Automatische Mails, die gesendet oder übersprungen wurden, stehen im Kimai-Log.
#### Behoben
- Unter PHP 8.1 ließ sich das Plugin nicht laden („Constant expression contains invalid operations“).
#### Hinweis
- Neue Tabelle für das Mail-Protokoll: nach dem Update `bin/console kimai:reload -n` und `bin/console kimai:bundle:drehzettel:install` ausführen.
- Das Release-Zip hängt jetzt am Release auf GitHub.

Alle Änderungen: https://github.com/shrippen/kimai-drehzettel-bundle/compare/v0.2.0...v0.3.0

## [0.2.0] — 2026-10-01

### English

The rules cover more of the TV FFS (AZV, travel days, staggered shoots, night shoots past midnight), the week mail can be edited and sent on a schedule, the pages are rebuilt on Kimai's own components, and the API gives clients like Plasmai what they need.

#### New
- AZV credit per TV FFS TZ 6: 2.5 h after 5 shooting days and 0.5 h for each further one, per block of 20 shooting days, counted per engagement from 1 May 2025; on the week page, the month PDF and the overview, switchable per engagement.
- Staggered shoot (TZ 5.6.3): a Sunday or one of four named holidays that is the 1st to 5th production day of the week gets no surcharge, with a warning on that day. A night shoot that ends by 04:00 belongs to the previous working day (TZ 5.2.4).
- Ruleset option: count travel days for the 6th/7th day and the weekly overtime (default: the tariff, they do not count).
- Per film day: extra pay (Zusatzgage/Spesen), the production's running shooting day number ("Drehtag 37") and the day of the shooting week (1 to 7) to override the 6th/7th-day count; shown as badges in the week view and in the PDF.
- Week mail: subject and text editable before sending, from a template per engagement with placeholders; automatic sending weekly or monthly at a set hour, by cron (`drehzettel:mail:due`) or, without cron, after a page load, never twice.
- Catering default per engagement.
- An engagement can be limited to activities, so a private commute on the same project is not counted as a film day.
- Built-in rulesets open read-only, to copy.
- API for external clients: list of engagements, day summary with pay, error codes, permissions and features in `ping`, default break and category, activity list, catering default and travel day setting of an engagement.
#### Improved
- Pages rebuilt on Kimai's components and the shared UI kit: page titles and actions, week navigation, key figures, forms in Kimai's dialogs, a confirmation before deleting; engagements can be deleted in the UI.
- The film day fields appear in Kimai's timesheet form as soon as a matching project is chosen, not only after saving.
- The film day note is Kimai's description of the entry: one field instead of two.
- Begin and end are rounded with Kimai's work rounding before anything is calculated, so a row adds up.
- The 12 h and 60 h limits are measured on net working time, without breaks and travel.
- Travel days no longer count for the 6th/7th day and the weekly overtime (TZ 12.1), unless the ruleset says so.
- PDF: dates in the user's language, amounts in the customer's currency, the signature image fits its box.
- The week table fits into 1280 px; German texts follow the shared glossary.
- Missing plugin migrations after an update show the install command instead of an error page; automatic mails wait for them.
#### Fixed
- Night minutes were one hour short when the clocks went back.
- An entry in another time zone than the user's could break the week page.
- Weekly overtime of a week across a month boundary was paid in both months.
- Saving the week view skipped Sunday and reset a day of the shooting week set elsewhere.
- Saving Kimai's timesheet form could store invalid edits or blank the day's film day data.
- A night shoot ending after midnight was merged with a later entry of that date.
- Wrong input (a year that is no number, an unknown ruleset) gave an error page instead of 404 or a form error.
#### Note
- New columns and tables: after updating, run `bin/console kimai:reload -n` and `bin/console kimai:bundle:drehzettel:install`. Stored film day notes move into the descriptions of the day's entries.

All changes: https://github.com/shrippen/kimai-drehzettel-bundle/compare/v0.1.0...v0.2.0

### Deutsch

Die Regeln decken mehr vom TV FFS ab (AZV, Reisetage, gestaffelter Dreh, Nachtdrehs über Mitternacht), die Wochen-Mail lässt sich bearbeiten und nach Plan versenden, die Seiten sind auf Kimais eigenen Bausteinen neu gebaut, und die API gibt Clients wie Plasmai, was sie brauchen.

#### Neu
- AZV-Gutschrift nach TV FFS TZ 6: 2,5 h nach 5 Drehtagen und 0,5 h für jeden weiteren, je Block von 20 Drehtagen, gezählt je Engagement ab 1. Mai 2025; auf der Wochenseite, im Monats-PDF und in der Übersicht, je Engagement abschaltbar.
- Gestaffelter Dreh (TZ 5.6.3): ein Sonntag oder einer von vier genannten Feiertagen, der 1. bis 5. Produktionstag der Woche ist, bekommt keinen Zuschlag, mit Warnung an dem Tag. Ein Nachtdreh, der bis 04:00 endet, gehört zum vorigen Arbeitstag (TZ 5.2.4).
- Regelwerk-Option: Reisetage für den 6./7. Tag und die Wochenmehrarbeit mitzählen (Standard: der Tarif, sie zählen nicht).
- Je Drehtag: Zusatzgage/Spesen, die laufende Drehtag-Nummer der Produktion („Drehtag 37“) und der Tag der Drehwoche (1 bis 7), um die Zählung für den 6./7. Tag zu überschreiben; als Badge in der Wochenansicht und im PDF.
- Wochen-Mail: Betreff und Text vor dem Senden bearbeitbar, aus einer Vorlage je Engagement mit Platzhaltern; automatischer Versand wöchentlich oder monatlich zu einer festen Stunde, per Cron (`drehzettel:mail:due`) oder ohne Cron nach einem Seitenaufruf, nie doppelt.
- Catering-Standard je Engagement.
- Ein Engagement lässt sich auf Tätigkeiten beschränken, damit ein privater Arbeitsweg im selben Projekt nicht als Drehtag zählt.
- Eingebaute Regelwerke öffnen schreibgeschützt, zum Kopieren.
- API für externe Clients: Liste der Engagements, Tagesübersicht mit Gage, Fehlercodes, Rechte und Features in `ping`, Standardpause und -kategorie, Tätigkeitsliste, Catering-Standard und Reisetag-Einstellung eines Engagements.
#### Verbessert
- Seiten auf Kimais Bausteinen und dem gemeinsamen UI-Kit neu gebaut: Seitentitel und -aktionen, Wochennavigation, Kennzahlen, Formulare in Kimais Dialogen, Rückfrage vor dem Löschen; Engagements lassen sich in der Oberfläche löschen.
- Die Drehtag-Felder erscheinen in Kimais Zeiterfassungsformular, sobald ein passendes Projekt gewählt ist, nicht erst nach dem Speichern.
- Die Drehtag-Notiz ist Kimais Beschreibung des Eintrags: ein Feld statt zwei.
- Beginn und Ende werden mit Kimais Arbeitszeit-Rundung gerundet, bevor gerechnet wird, damit eine Zeile aufgeht.
- Die Grenzen 12 h und 60 h gelten für die reine Arbeitszeit, ohne Pausen und Reisezeit.
- Reisetage zählen nicht mehr für den 6./7. Tag und die Wochenmehrarbeit (TZ 12.1), außer das Regelwerk sagt es.
- PDF: Datum in der Sprache des Benutzers, Beträge in der Währung des Kunden, das Unterschriftsbild passt in sein Feld.
- Die Wochentabelle passt in 1280 px; die deutschen Texte folgen dem gemeinsamen Glossar.
- Fehlen nach einem Update Plugin-Migrationen, zeigen die Seiten den Installationsbefehl statt einer Fehlerseite; automatische Mails warten darauf.
#### Behoben
- Nachtminuten fehlten eine Stunde, wenn die Uhr zurückgestellt wurde.
- Ein Eintrag in einer anderen Zeitzone als der des Benutzers konnte die Wochenseite zum Absturz bringen.
- Wochenmehrarbeit einer Woche über einen Monatswechsel wurde in beiden Monaten bezahlt.
- Speichern der Wochenansicht übersprang den Sonntag und setzte einen anderswo gesetzten Tag der Drehwoche zurück.
- Speichern in Kimais Zeiterfassungsformular konnte ungültige Änderungen speichern oder die Drehtag-Daten des Tages leeren.
- Ein Nachtdreh, der nach Mitternacht endet, wurde mit einem späteren Eintrag desselben Datums zusammengelegt.
- Falsche Eingaben (ein Jahr, das keine Zahl ist, ein unbekanntes Regelwerk) gaben eine Fehlerseite statt 404 oder einem Formularfehler.
#### Hinweis
- Neue Spalten und Tabellen: nach dem Update `bin/console kimai:reload -n` und `bin/console kimai:bundle:drehzettel:install` ausführen. Gespeicherte Drehtag-Notizen wandern in die Beschreibungen der Einträge des Tages.

Alle Änderungen: https://github.com/shrippen/kimai-drehzettel-bundle/compare/v0.1.0...v0.2.0

## [0.1.0] — 2026-09-24

### English

The first release, for trial use in production: the TV FFS rules applied to your Kimai timesheets, with a week view, PDF timesheets and an API.

#### New
- TV FFS rules built in and editable: daily and weekly overtime, night work, Saturday, Sunday and holiday surcharges, break rule.
- Engagements: pay, dates and a ruleset snapshot per user and project.
- Week view and rules editor, with compliance warnings (more than 12 h a day or 60 h a week, rest time).
- Weekly and monthly timesheet PDF, with mail to the production.
- Public holidays detected automatically (together with the Working Hours & Holidays plugin, optional).
- Film day data in Kimai's own timesheet form.
- Versioned REST API for external clients (`/api/drehzettel/...`).
#### Note
- Needs Kimai 2.67 or newer and PHP 8.1 or newer. Install into `var/plugins/DrehzettelBundle` (the folder name must stay), then run `bin/console kimai:reload -n` and `bin/console kimai:bundle:drehzettel:install`.
- No payroll or legal advice: check every result against your contract.

All changes: https://github.com/shrippen/kimai-drehzettel-bundle/commits/v0.1.0

### Deutsch

Die erste Version, zum Ausprobieren im Betrieb: die Regeln des TV FFS auf deine Kimai-Zeiteinträge angewandt, mit Wochenansicht, Stundenzetteln als PDF und einer API.

#### Neu
- TV-FFS-Regeln eingebaut und bearbeitbar: tägliche und wöchentliche Mehrarbeit, Nachtarbeit, Zuschläge für Samstag, Sonntag und Feiertage, Pausenregel.
- Engagements: Gage, Zeitraum und eine Kopie des Regelwerks je Benutzer und Projekt.
- Wochenansicht und Regel-Editor, mit Warnungen (mehr als 12 h am Tag oder 60 h in der Woche, Ruhezeit).
- Wöchentlicher und monatlicher Stundenzettel als PDF, mit Mail an die Produktion.
- Feiertage werden automatisch erkannt (zusammen mit dem Plugin Arbeitszeiten & Feiertage, optional).
- Drehtag-Daten in Kimais eigenem Zeiterfassungsformular.
- Versionierte REST-API für externe Clients (`/api/drehzettel/...`).
#### Hinweis
- Braucht Kimai 2.67 oder neuer und PHP 8.1 oder neuer. Nach `var/plugins/DrehzettelBundle` installieren (der Ordnername muss bleiben), dann `bin/console kimai:reload -n` und `bin/console kimai:bundle:drehzettel:install` ausführen.
- Keine Lohn- oder Rechtsberatung: prüfe jedes Ergebnis gegen deinen Vertrag.

Alle Änderungen: https://github.com/shrippen/kimai-drehzettel-bundle/commits/v0.1.0
