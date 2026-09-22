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
Console commands run as root and write cache files as root; Apache's www-data worker
then can't overwrite them (500s on the next page load). Fix: `docker compose -f dev/compose.yaml
exec -T kimai chown -R www-data:www-data /opt/kimai/var/cache /opt/kimai/var/data` (done automatically
by `dev/reset.sh`).
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

- [x] One PDF type, period: week, month, free range (weeks always calculated whole)
- [x] Optional columns and sections, defaults per engagement, changeable at export
- [x] Signature image on the crew line
- [x] Rounding note in footer
- [x] File name `Timesheet_<Surname>_<Project>_<from>-<to>.pdf`
- [x] Under-time column, all weekdays, weekly overtime line, remarks from film days (all seen on the app's own timesheets)
- [x] German and English, `--locale` override
- [x] `drehzettel:pdf` command, checked on the dev instance against timesheets of the app

### Phase 4 — UI

- [x] Week view: edit break, catering, day type, category, note; live preview via AJAX
- [x] Ruleset management (copy a preset or an existing custom ruleset, edit, delete)
- [x] Engagement management (create, edit, edit rules, delete) for `drehzettel_manage`
- [x] Menu entry for users with the `drehzettel` permission who have at least one engagement, or who can manage
- [x] Permissions: `drehzettel` (own), `drehzettel_manage` (all)
- [x] Translations de/en (112 keys)
- [x] Signature upload, checked by content (`getimagesizefromstring`), not by extension
- [x] Checked end to end on the dev instance: login, overview, week, save, reload, week/month PDF, preview, engagement and ruleset creation, signature upload and rejection of a non-image file

### Phase 5 — Compliance and extras

- [x] Warnings: > 12 h/day, > 60 h/week, rest time < 11 h (11.5 h after a begun 12th hour), shown on the week page
- [x] Mail the week's PDF to the production office, remembers the last address per engagement
- [ ] Reduced weekly pay contract (80 %) — deferred. TV FFS 5.4.4.2 routes hours 41-50 through the weekly
      mechanism but hours beyond 50 through the *daily* tier mechanism (5.4.3.2), which the current
      `Ruleset`/`WeekCalculator` split (independent daily tiers per day, one weekly pool) cannot express
      without a real risk of an incorrect pay result. Needs a small model change, not a stopgap.
- [ ] Holiday plugin integration for public holidays — deferred. `DayCategory::HOLIDAY` already exists and
      can be set by hand on a film day; wiring it to HolidayBundle's `PublicHolidayRepository` automatically
      needs an optional cross-plugin dependency (HolidayBundle may not be installed), which needs a safe DI
      pattern (service locator / `class_exists` guard) that hasn't been designed yet.
- [ ] Time account (AZV day) — deferred. A real accrual ledger (2.5 h + 30 min per consecutive shooting day,
      TV FFS §6), not a one-line addition; out of scope for this pass.

## Open

- Pause over 45 min: TV FFS counts the excess as work time, the app deducts it fully. Ruleset option.
- Rest-time compliance only checks days inside one calculated week; the gap across a week boundary
  (last day of one week to the first day of the next) is not checked.
- `MailConfiguration` needs a "from" address configured in Kimai (system settings), otherwise
  `KimaiMailer` throws. Checked on the dev instance with the `null://` transport (no real send).
- "Begun hour" surcharge reading (TV FFS 5.4.3.2): default in TV FFS preset is round up.
- Interplay with Holiday plugin target hours.
- Under-time (`Unterstunden`): assumed to be the work time missing to 8 h on a shooting day. The app's info text could not be read, and no sample sheet has a day under 8 h with that column.
- Unverified against real PDFs (no example with these cases): weekly overtime above 50 h, 6th/7th day, Sunday/holiday pay, Saturday pay. The "Like TimeSheet app" preset copies the app's settings, but assumes: 6th/7th day surcharges stack with Saturday/Sunday surcharges; Sunday/holiday use the day rate.
- Half-cent ties: the app shows 578.125 as 578.12; the plugin rounds half up (578.13). Tests allow 1 cent there.
- Daily gage pays at least a full day (7:15 h -> 400.00 EUR in the daily-gage example). Weekly gage pays worked time.
- Timesheet entries of one date are merged into one span (earliest begin to latest end). Gaps between entries are not treated as break.
- Holidays are not detected yet; category comes from the weekday or the film day override.
