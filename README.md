# Drehzettel

Kimai plugin for employed film crew: track the shooting day once in Kimai, get the timesheet the production office asks for.

Landing page: <https://shrippen.github.io/kimai-drehzettel-bundle/>

> **Early development.** Calculation core and storage layer work and are tested. PDF export and the user interface are still to come. Vibe-coded, no payroll or legal advice: check every result against your contract.

## What it does

- TV FFS rules built in and editable: daily and weekly overtime, night work, Saturday, Sunday and holiday surcharges, break rule
- Engagements: pay, dates and a ruleset snapshot per user and project, so two people on one film can have different terms
- Rounding of work time and surcharges: minute, quarter hour, half hour, hour; up, down or nearest
- Time comes from normal Kimai timesheet entries, one per shooting day. Break, catering, day type, extra pay and note are stored per film day
- REST API for external clients (e.g. the Plasmai widget), see [API](#api)
- Planned: weekly and monthly PDF with optional columns, warnings for working and rest time limits

## Install

Needs Kimai 2.67 or newer and PHP 8.1 or newer.

```
git clone https://github.com/shrippen/kimai-drehzettel-bundle.git var/plugins/DrehzettelBundle
bin/console kimai:reload -n
bin/console kimai:bundle:drehzettel:install
```

The folder name must stay `DrehzettelBundle`. In the official Docker image use `/opt/kimai/bin/console` with `docker exec`.

Until the interface exists, engagements are created in code. `bin/console drehzettel:week USER PROJECT-ID YEAR WEEK` prints a calculated week.

## 6th and 7th day

Day N is the n-th working day of the ISO calendar week (TV FFS TZ 5.4.3.1/5.4.3.4: "Arbeit am 6. und 7. Tag der Kalenderwoche"), 1-7. A day off does not reset the count, and weeks never carry over: Mon, Tue, (Wed off), Thu-Sun makes Sunday day 6; Wed-Tue worked makes Monday and Tuesday day 1 and 2 of the new week. A day is the local date an entry begins on. Travel days are no working days: travel time is paid "wie normale Arbeitszeit ohne Zuschläge" (TZ 12.1) and is no working time (Produktionsallianz FAQ), so a travel day neither advances N nor counts toward weekly overtime. Mon travel, Tue-Sun shooting makes Sunday day 6.

`productionDay` ("Zuschlagstag", surcharge day) overrides N for that day only, 1-7. The week page shows "Tag N der Woche" next to the date from day 6 or when overridden; the override field shows the counted N as placeholder.

What day 6 and 7 pay comes from the ruleset: TV FFS pools them into weekly overtime, the quarter-hour preset adds 25 % / 50 %. Either comes on top of Saturday, Sunday and holiday surcharges.

## API

Under Kimai's own `/api`, same `Authorization: Bearer <token>`, listed in `/api/doc`. Money in integer cents, dates `YYYY-MM-DD`. Clients must ignore unknown keys: v1 only ever gains keys. `user` defaults to the token owner; another user needs `drehzettel_manage`.

| Request | Answer |
|---|---|
| `GET /api/drehzettel/ping` | `{installed, pluginVersion, apiVersions: ["v1"], permissions: {view, manage}, features: ["errorCodes", "engagements", "defaults", "extraPay", "daySummary", "shootingDayNumber"]}` |
| `GET /api/drehzettel/v1/engagements?date=&user=` | engagements active on `date` (default today): `[{engagementId, projectId, projectName, customerName, rulesetName, crewRole, validFrom, validTo, toggleDefault}]` |
| `GET /api/drehzettel/v1/engagement-status?project=&date=&user=` | `{active, engagementId, toggleDefault, rulesetName}`; no engagement is `active: false`, not 404 |
| `GET /api/drehzettel/v1/film-days/{date}?project=&user=` | stored fields, see below |
| `PUT /api/drehzettel/v1/film-days/{date}?project=&user=` | partial update: only sent keys change; answers like `GET` |
| `GET /api/drehzettel/v1/days/{date}/summary?project=&user=` | calculated day, see below |

Film day (`GET`/`PUT`):

```
{"date": "2026-09-14", "engagementId": 6, "breakMinutes": null, "catering": true,
 "category": null, "note": null, "dayType": "workday", "productionDay": null,
 "extraPayCents": 2500, "shootingDayNumber": 37, "defaultBreakMinutes": 45,
 "effectiveCategory": "workday"}
```

- `breakMinutes` 0-720, null = `defaultBreakMinutes` of the engagement's ruleset
- `category` `workday|saturday|sunday|holiday`, null = automatic; `effectiveCategory` is what applies (weekday, public holiday from the Holiday plugin, or the stored value)
- `dayType` `workday|travel`; `catering` boolean
- `productionDay` 1-7 or null, "Surcharge day" (Zuschlagstag): overrides the day of the calendar week behind the 6th/7th-day surcharge, null = automatic, see [6th and 7th day](#6th-and-7th-day)
- `shootingDayNumber` 1-999 or null, "Production shooting day" (Drehtag der Produktion, "Drehtag 37"): running counter across the production, informational only, no effect on any figure
- `extraPayCents` 0-10,000,000: Zusatzgage/Spesen, added to the day's pay as is; null resets to 0
- `note` up to 500 characters

Day summary: `{date, engagementId, hasEntry, begin, end, workMinutes, breakMinutes, overtime: [{percent, minutes}], nightMinutes, underMinutes, category, categoryPercent, dayNumber, shootingDayNumber, weeklyOvertimeMinutes, warnings: [{issue, minutes, limitMinutes}], payCents, extraPayCents, currency}`. `dayNumber` is the day of the calendar week behind the 6th/7th-day surcharge (counted or `productionDay`). `payCents` is the day's pay including extra pay and excluding weekly overtime; null without a gage.

Errors are `{"error": "...", "code": "..."}`:

| Status | `code` |
|---|---|
| 400 | `missing_project`, `invalid_project`, `invalid_user`, `invalid_date`, `invalid_json`, `invalid_value` |
| 403 | `forbidden` |
| 404 | `unknown_project`, `unknown_user`, `no_engagement` |

Malformed JSON never reaches the plugin: Kimai answers it with its own `{"code": 400, "message": "Bad Request"}`.

## Development

```
php tests/run.php                                   # unit tests, no Kimai needed
docker compose -f dev/compose.yaml up -d            # local Kimai 2.67 on http://localhost:8091
```

See [`roadmap.md`](roadmap.md) for the dev workflow, phases and open questions, [`Design.md`](Design.md) for the visual identity. Pages inside Kimai follow the [kimai-plugin-ui guidelines](https://github.com/shrippen/kimai-plugin-ui/blob/main/GUIDELINES.md).

## License

GPL-3.0-or-later
