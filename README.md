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
- Warnings for working and rest time limits, see [Working and rest time warnings](#working-and-rest-time-warnings)

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

Ruleset option "Reisetage beim 6./7. Tag und bei Wochenmehrarbeit" (`travelDays`): `excluded` (default, the tariff reading above) or `counted`, for a deviating agreement. Counted, a travel day advances N and its time feeds weekly overtime (and gets a fixed 6th/7th-day surcharge where the ruleset has one); it still gets no daily, night, Saturday, Sunday or holiday surcharge (TZ 12.1) and still counts 0 for the working-time warnings and AZV. Mon travel, Tue-Sun shooting then makes Sunday day 7. Rulesets stored before the option read as `excluded`.

`productionDay` ("Zuschlagstag", surcharge day) overrides N for that day only, 1-7. The week page shows "Tag N der Woche" next to the date from day 6 or when overridden; the override field shows the counted N as placeholder.

What day 6 and 7 pay comes from the ruleset: TV FFS pools them into weekly overtime, the quarter-hour preset adds 25 % / 50 %. Either comes on top of Saturday, Sunday and holiday surcharges.

## AZV credit

Arbeitszeitverkürzung per TV FFS TZ 6, credit only (taking AZV days is not modelled yet):

| Shooting days | Credit |
|---|---|
| 1-4 | 0 |
| 5 | 2.5 h (TZ 6.1) |
| each further one up to 20 | + 0.5 h, 10 h after 20 days = one AZV day (TZ 6.2/6.3) |
| 21-25, 26, 27-40, ... | the same per block of 20, but the 2.5 h for days 21-25 come "ab dem 26. Drehtag" (TZ 6.4): 25 days = 10 h, 26 = 13 h. The Produktionsallianz FAQ credits them with day 25 ("Erst ab 25 Drehtagen"); the plugin follows the tariff text |

- A shooting day is a working-day entry of the engagement, any length. Travel days and days off do not count and do not break the row (TZ 6.1 footnote 2: "an mindestens 5 aufeinanderfolgenden Drehtagen"). The row ends with the engagement.
- Applies to crew behind the camera, not to high-frequency series (TZ 6.1, 6.5), for shoots from 2025-05-01 (TZ 6.7). Default: on for engagements with the TV FFS 2024 ruleset starting on or after 2025-05-01; the engagement form changes it (e.g. a production running before May 2025 with fewer than 5 shooting days). Days before 2025-05-01 never count.
- Shown on the week page (work time tile, up to the end of the week), in the PDF (up to the end of the period) and on the overview (up to today or the engagement end).

## Working and rest time warnings

Warnings only, they never change a figure (the tariff attaches no consequence).

| Check | Rule |
|---|---|
| Daily maximum | working time over 12 h (TZ 5.2.5) |
| Weekly maximum | working time over 60 h in the calendar week (TZ 5.2.5) |
| Rest time | under 11 h from the end of one entry to the begin of the next; 11.5 h after a day with a begun 12th hour, i.e. over 11:00 working time (TZ 5.9.1) |

Working time is net: presence minus the break up to the ruleset's free break (TZ 5.8.2: breaks up to 45 min are no working time), unrounded. Travel days count 0 (TZ 12.1; the Produktionsallianz FAQ reads the 12th hour as "reine Arbeitszeit, also ohne Pausen oder Wegezeiten"). 07:00-18:45 with 45 min break is 11:00 net and needs 11 h rest; 07:00-18:46 needs 11.5 h.

## API

Under Kimai's own `/api`, same `Authorization: Bearer <token>`, listed in `/api/doc`. Money in integer cents, dates `YYYY-MM-DD`. Clients must ignore unknown keys: v1 only ever gains keys. `user` defaults to the token owner; another user needs `drehzettel_manage`.

| Request | Answer |
|---|---|
| `GET /api/drehzettel/ping` | `{installed, pluginVersion, apiVersions: ["v1"], permissions: {view, manage}, features: ["errorCodes", "engagements", "defaults", "extraPay", "daySummary", "shootingDayNumber", "azv", "travelDays"]}` |
| `GET /api/drehzettel/v1/engagements?date=&user=` | engagements active on `date` (default today): `[{engagementId, projectId, projectName, customerName, rulesetName, crewRole, validFrom, validTo, toggleDefault, azvEligible, travelDays}]`; `travelDays` is the ruleset option `excluded` or `counted` |
| `GET /api/drehzettel/v1/engagements/{id}/azv?date=` | AZV credit up to and including `date` (default today), see below |
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

Day summary: `{date, engagementId, hasEntry, begin, end, workMinutes, breakMinutes, overtime: [{percent, minutes}], nightMinutes, underMinutes, category, categoryPercent, dayNumber, shootingDayNumber, weeklyOvertimeMinutes, warnings: [{issue, minutes, limitMinutes}], payCents, extraPayCents, currency, azvMinutesToDate}`. `dayNumber` is the day of the calendar week behind the 6th/7th-day surcharge (counted or `productionDay`). `payCents` is the day's pay including extra pay and excluding weekly overtime; null without a gage. `azvMinutesToDate` is the AZV credit up to and including the date, null when the engagement earns none.

AZV: `{engagementId, eligible, countsFrom, date, shootingDays, minutes, days, openMinutes, dayMinutes: 600, blockDays: 20}`; `days` = whole AZV days (`minutes` / 600), `openMinutes` the rest. Not eligible: `eligible: false`, `countsFrom: null`, zeros. Another user's engagement needs `drehzettel_manage` (403); unknown id is 404 `unknown_engagement`. See [AZV credit](#azv-credit).

Errors are `{"error": "...", "code": "..."}`:

| Status | `code` |
|---|---|
| 400 | `missing_project`, `invalid_project`, `invalid_user`, `invalid_date`, `invalid_json`, `invalid_value` |
| 403 | `forbidden` |
| 404 | `unknown_project`, `unknown_user`, `no_engagement`, `unknown_engagement` |

Malformed JSON never reaches the plugin: Kimai answers it with its own `{"code": 400, "message": "Bad Request"}`.

## Development

```
php tests/run.php                                   # unit tests, no Kimai needed
docker compose -f dev/compose.yaml up -d            # local Kimai 2.67 on http://localhost:8091
```

See [`roadmap.md`](roadmap.md) for the dev workflow, phases and open questions, [`Design.md`](Design.md) for the visual identity. Pages inside Kimai follow the [kimai-plugin-ui guidelines](https://github.com/shrippen/kimai-plugin-ui/blob/main/GUIDELINES.md).

## License

GPL-3.0-or-later
