# TimeSheet-App (de.dycontech.TimeSheet 2.2.0) – Analyse

Quelle: App auf einem Android-Gerät (nur lesend) + 12 exportierte Stundenzettel. Die PDFs liegen lokal und nicht im Repo, die Zahlen stehen als anonymisierte Fixtures in `tests/fixtures/`.

## Navigation
Tag · Woche · Projekte · Stundenzettel · Benutzerdaten · Benutzerverwaltung · Einstellungen · Import/Export · Support · Info

## Globale Einstellungen
- Arbeitszeit auf volle X Minuten runden (Stufen; aktuell **15 min**)
- Pausenzeiten in Berechnungen ignorieren (aus)
- Stundenzettel nach Erzeugung anzeigen/teilen
- Tag beim Seitenwechsel automatisch speichern (aus)
- Warnung über ausgeblendete Spalten mit Inhalt
- Gesamte Arbeitszeit im Projekt anzeigen

## Projekt (3-Seiten-Wizard)
1. Name, Produktionsnummer, Produktionsfirma, Tätigkeit, Vergütungsart (Wochengage / Tagesgage)
2. Gage; 3 Tages-Überstundenstufen (Schwelle in h + Zuschlag %); Nachtzuschlag; 2 Wochen-Überstundenstufen
3. Reisetag-Vergütung (%), Überstundenvergütung Reisetag (an/aus), Ladetag-Vergütung (%), Überstundenvergütung Ladetag, Standard-Pause, Catering (an/aus), Catering-Abzug pro Tag (€)

| Parameter | Wochengage-Projekt (1.581 €) | Tagesgage-Projekt (400 €) |
|---|---|---|
| Tagesstufe 1 | nach 10 h, +25 % | nach 8 h, +0 % |
| Tagesstufe 2 | nach 11 h, +50 % | nach 10 h, +25 % |
| Tagesstufe 3 | nach 13 h, +100 % | (+100 %) |
| Nachtzuschlag | +25 %, Fenster ab 22:00 | +25 % |
| Wochenstufe 1 | nach 50 h, +25 % | – |
| Wochenstufe 2 | nach 55 h, +50 % | – |
| Reise-/Ladetag | 50 % | – |
| Pause | 00:45 | 00:45 |
| Catering-Abzug | 9,50 € | – |

## Tag-Erfassung
- Beginn, Ende, Pause; Arbeitszeit = Ende − Beginn − Pause
- Anzeige: Arbeitszeit, Nachtarbeit, +25/+50/+100 %-Minuten, Verdienst (unverbindlich)
- Tageskategorie **Werktag / Samstag +25 % / Sonntag +75 % / Feiertag +100 %**
- Drehtag-Zähler **1.–5. Tag / 6. Tag +25 % / 7. Tag +50 %**
- Catering (an/aus)
- Tagestyp: Arbeitstag, Halber Tag, Reisetag, Ladetag, Abrechnung nach Stundenlohn, Bezahlter freier Tag, Krankheitstag
- Zusatzgage/Spesen (€), Anmerkung zum Tag

## Wochen-/Stundenzettel-Export
Optionen: Nachtarbeit, Wochenüberstunden, Pausen, Tagesanmerkungen, Unterschrift, Unterschriftsfeld Produktionsleitung, alle Wochentage, Unterstunden, Gehalt ausblenden (3 Stufen), Tagesüberstundenspalten (Anzahl), **1–5 Wochen pro Zettel** (= Monats-PDF).
Dateiname: `Timesheet_<Nachname>_<Projekt>_<JJJJMMTT>-<JJJJMMTT>.pdf`, Ablage pro Projekt.
Unterschrift: Bilddatei (.jpg/.png) in Benutzerdaten.

## Nachgerechnetes Berechnungsmodell (stimmt mit PDFs überein)
- Stundensatz = Wochengage / 50 h (1.581 / 50 = 31,62 €)
- Tag A: 08:30–17:30, Pause 0 → 9:00 h → 9 × 31,62 = 284,58 € ✓
- Tag B: 11:45 h → 1:00 h @ +25 %, 0:45 h @ +50 % (Schwellen 10 h / 11 h) ✓
- Nacht: Minuten nach 22:00, z. B. Ende 22:30 → 0:30 h, +25 % ✓
- Tag C: 8,75 h × 31,62 + 0,5 h × 31,62 × 25 % = 280,63 € ✓
- Catering „Ja“: −9,50 € (Tag D: 280,63 − 9,50 = 271,13 €) ✓
- Tagesgage-Projekt: Stundensatz = 400 / 8 h = 50 €; 11:15 h → 8 h Basis + 2:00 @ +0 % + 1:15 @ +25 % = 578,12 € ✓
- Pausen im Beispiel: Default 0:45, manuell überschreibbar (0:00 an zwei Tagen, 1:15 an einem Tag)
- Arbeitszeit auf 15 min gerundet

## Nicht abgedeckt / nicht geprüft
- Wochenüberstunden-Zeilen mit Werten (kein PDF überschreitet 50 h)
- Feiertag-/Sonntagslayout im PDF (kein Beispiel)
- Reisetag-/Ladetag-Zeilen im PDF (kein Beispiel)

## TV FFS (12.10.2024) – Regelwerk-Defaults

Quelle: TV FFS Text, TZ 5.x (ver.di). Stundengage = 1/50 Wochengage bzw. 1/10 Tagesgage (TZ 5.7.1).

| Regel | TV FFS | App (Wochengage-Projekt) |
|---|---|---|
| Höchstarbeitszeit | 12 h/Tag, 60 h/Woche (5.2.5) | – |
| Tagesmehrarbeit | 11. Std. +25 %, ab vollendeter 11. Std. +50 % (5.4.3.2) | 10 h +25 %, 11 h +50 %, 13 h +100 % |
| Wochenmehrarbeit | 51.–55. Std. +25 %, danach +50 %; Tagesmehrarbeit zählt nicht mit (5.4.3.3) | 50 h +25 %, 55 h +50 % |
| 6./7. Tag der KW | wie Wochenmehrarbeit (5.4.3.4) | 6. Tag +25 %, 7. Tag +50 % |
| Nacht | 22–6 Uhr +25 %, zusätzlich zum Mehrarbeitszuschlag (5.5) | 22:00, +25 % |
| Samstag | +25 % (5.6.4) | +25 % |
| Sonntag / Feiertag | +75 % / +100 % auf Tagesgage (5.6.3, 5.7.3); Ausnahme versetzter Dreh | +75 % / +100 % |
| Pause | ≥ 45 min; bis 45 min keine Arbeitszeit (5.8) | Default 0:45, frei überschreibbar |
| Ruhezeit | 11 h, ab begonnener 12. Std. 11,5 h (5.9.1) | – |
| Reisezeit | wie Arbeitszeit ohne Zuschläge (12.1) | Reisetag 50 % (individuell) |
| Verminderte Wochengage | 80 %, Mehrarbeit ab 8. Std., 41.–50. Std. +25 % (5.3.3, 5.4.4) | – |

Abweichungen der App vom TV FFS: 3. Stufe (13 h, +100 %), Reisetag 50 %, ganze Pause > 45 min abgezogen (TV FFS: Überschuss zählt als Arbeitszeit). Diese Werte sind individuell, das Plugin muss sie über das Regelwerk abbilden können.

Offen: „angefangene Stunde“ (5.4.3.2) – minutengenau (wie App) oder aufgerundet?
