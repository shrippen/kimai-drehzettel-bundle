# TODO

✅ = live reproduziert, 📖 = aus Code-Review

## P0
- [x] ✅ Sonntag beim Wochen-Speichern: '!Y-m-d' mit User-Zeitzone + Test (WeekController.php:67)
- [x] ✅ API-PUT als Partial-Update (nur übergebene Felder), dayType/productionDay in GET/PUT (DrehzettelApiController.php:130)
- [x] ✅ API-Validierung: breakMinutes 0–720, note ≤500, unbekannte category/dayType → 400 (DrehzettelApiController.php:312); negative Pause in der Berechnung abfangen

## P1
- [x] 📖 Timesheet-Form: existierenden Drehtag immer vorbefüllen, auch bei Duplicate/Neuanlage (TimesheetFormExtension.php:87)
- [x] 📖 Kein flush() im POST_SUBMIT; persist bzw. Timesheet*PostEvent-Subscriber (TimesheetFormExtension.php:186)
- [x] 📖 Wochen-Überstunden nur einem Monat zuordnen (TimesheetViewBuilder::sums) + Test
- [x] 📖 Admin-Edit mit geändertem User/Projekt/Datum: Engagement neu ermitteln

## P2
- [x] 📖 Timesheet-Zeitzone vs. User-Zone (DayInputBuilder::spans): Umrechnen in User-Zone verworfen (Kimai zeigt Einträge in ihrer eigenen Zone, alle Zeiten der Dev-Daten würden sich verschieben). Stattdessen Abfrage ±1 Tag, Filter nach lokalem Datum; live reproduziert: Berlin-Eintrag Mo 00:30 bei User-Zone UTC → Woche 40 gab 500
- [x] 📖 DST bei Nachtminuten (DayCalculator.php:96) + Test
- [ ] 📖 500er: EngagementController::edit Datum (:88), PayKind::from (:165), RulesetController::new unbekanntes {from} (:48), \d+ bei {year}/{week}
- [ ] 📖 Kleinkram: Custom-Ruleset-Name kollidiert mit Preset-Key, hardcodiertes /api im Banner-JS (ThemeSubscriber.php:78), Mail-Flash leakt Exception-Message, WeekCommand Engagement mitten in der Woche, verwaiste Drehtage nach API-PATCH
- [x] kein Fehler: Signatur >48 KB (LONGTEXT, live 240 KB gespeichert)
