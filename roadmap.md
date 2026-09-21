# Drehzettel Roadmap

Kimai plugin for employed film crew work: TV FFS rules, weekly and monthly timesheet PDFs.
Target: Kimai 2.67, PHP ^8.1.

## Concept

```
Ruleset (system)           Engagement                  Project
"TV FFS 2024" (built-in)   User x Project              "Sample Film"
"Like TimeSheet app"  ───► pay (weekly/daily)     ───► not billable,
custom, copyable           valid from-to                no freelance client
                           role
                           ruleset snapshot (editable)
```

- Rules live on the **engagement**, not the project. Two crew members on one project may have different rules.
- A project without an engagement behaves like plain Kimai. The plugin stays inert.
- Ruleset edits never change past results: engagements hold a snapshot.
- One continuous shooting day = one Kimai timesheet entry. Break comes from the ruleset default, overridable per day.

## Rounding

Two independent settings per ruleset. Unit: minute, 15 min, 30 min, hour. Direction: up, down, nearest.

| Setting | Applies to |
|---|---|
| Work time rounding | net work time per day |
| Surcharge rounding | each surcharge share (night, daily tiers, weekly tiers) |

Order: round work time, split into tiers, round each surcharge share. Surcharge rounding never changes work time.

## Dev environment

Local Kimai 2.67 in Docker. Plugin is bind-mounted read-only.

```
docker compose -f dev/compose.yaml up -d
# http://localhost:8001   admin@example.test / admin-dev-pass
docker compose -f dev/compose.yaml exec -T kimai /opt/kimai/bin/console kimai:reload
docker compose -f dev/compose.yaml exec -T kimai /opt/kimai/bin/console kimai:bundle:drehzettel:install
docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/seed.php
docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/check.php
docker compose -f dev/compose.yaml exec -T kimai /opt/kimai/bin/console drehzettel:week admin 1 2025 21
```

Run `kimai:reload` after every change to service or config files (prod cache).
Unit tests need no Kimai: `php tests/run.php`.

## Reference data

Timesheets exported from the TimeSheet app were the reference. The PDFs stay local (`reference/`, git-ignored: they carry a name and a signature). Their numbers live on as anonymized fixtures in `tests/fixtures/`, with dates shifted by 52 weeks. Details in `docs/timesheet-app-analyse.md`.

## Phases

### Phase 1 — Core

- [x] `agent.md`, `Design.md`, `roadmap.md`
- [x] Bundle skeleton (composer.json, bundle class, DI extension)
- [x] Value objects: ruleset, tiers, rounding, day input, day result
- [x] Presets: TV FFS 2024, "Like TimeSheet app"
- [x] Day calculator: work time, daily tiers, night, Saturday/Sunday/holiday, catering, pay
- [x] Week calculator: weekly tiers (>50 h, >55 h), 6th and 7th day
- [x] Tests against fixtures from exported timesheets (`php tests/run.php`)

### Phase 2 — Persistence

- [x] Entities: ruleset template, engagement, film day (break, catering, category, day type, production day, note)
- [x] Migration and install command (`kimai:bundle:drehzettel:install`)
- [x] Ruleset codec (array/JSON), used for the engagement snapshot
- [x] Timesheet entry to day input mapping (`DayInputBuilder`)
- [x] Engagement service: open with snapshot, overlap check, active lookup
- [x] Week and period service (period split by ISO week)
- [x] Dev environment and integration checks (`dev/`)
- [ ] Edit rules of an existing engagement (override) — moves to Phase 4 with the UI

### Phase 3 — PDF

- [ ] One PDF type, period: week, month, free range
- [ ] Optional columns, defaults per engagement, changeable at export
- [ ] Signature image on crew line
- [ ] Rounding note in footer
- [ ] File name `Timesheet_<Surname>_<Project>_<from>-<to>.pdf`

### Phase 4 — UI

- [ ] Week view: edit break, catering, day type; live totals
- [ ] Ruleset and engagement management
- [ ] Menu entry only for users with an active engagement
- [ ] Permissions: own engagement, manage engagements
- [ ] Translations de/en

### Phase 5 — Compliance and extras

- [ ] Warnings: > 12 h/day, > 60 h/week, rest time < 11 h (11.5 h after 12th hour)
- [ ] Reduced weekly pay contract (80 %)
- [ ] Holiday plugin integration for public holidays
- [ ] Time account (AZV day)
- [ ] Mail PDF to production

## Open

- Pause over 45 min: TV FFS counts the excess as work time, the app deducts it fully. Ruleset option.
- "Begun hour" surcharge reading (TV FFS 5.4.3.2): default in TV FFS preset is round up.
- Interplay with Holiday plugin target hours.
- Unverified against real PDFs (no example with these cases): weekly overtime above 50 h, 6th/7th day, Sunday/holiday pay, Saturday pay. The "Like TimeSheet app" preset copies the app's settings, but assumes: 6th/7th day surcharges stack with Saturday/Sunday surcharges; Sunday/holiday use the day rate.
- Half-cent ties: the app shows 578.125 as 578.12; the plugin rounds half up (578.13). Tests allow 1 cent there.
- Daily gage pays at least a full day (7:15 h -> 400.00 EUR in the daily-gage example). Weekly gage pays worked time.
- Timesheet entries of one date are merged into one span (earliest begin to latest end). Gaps between entries are not treated as break.
- Holidays are not detected yet; category comes from the weekday or the film day override.
