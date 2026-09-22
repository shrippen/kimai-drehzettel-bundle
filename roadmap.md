# Drehzettel Roadmap

Kimai plugin for employed film crew work: TV FFS rules, weekly and monthly timesheet PDFs.
Target: Kimai 2.67, PHP ^8.1.

## Concept

```
Ruleset (system)           Engagement                  Project
"TV FFS 2024" (built-in)   User x Project              "Sample Film"
"Quarter-Hour Ruleset" ──► pay (weekly/daily)     ───► not billable,
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
# http://localhost:8091   admin@example.test / admin-dev-pass
docker compose -f dev/compose.yaml exec -T --user www-data kimai /opt/kimai/bin/console kimai:reload
docker compose -f dev/compose.yaml exec -T --user www-data kimai /opt/kimai/bin/console kimai:bundle:drehzettel:install
docker compose -f dev/compose.yaml exec -T --user www-data kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/seed.php
docker compose -f dev/compose.yaml exec -T --user www-data kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/check.php
docker compose -f dev/compose.yaml exec -T --user www-data kimai /opt/kimai/bin/console drehzettel:week admin 1 2025 21
```

Run `kimai:reload` after every change to service or config files (prod cache).
**Always pass `--user www-data`** to `docker compose exec`. The default exec user is root; a
console command run as root writes cache and font files owned by root, and Apache's own
www-data worker then can't overwrite them — the next page load 500s with "not writable". If
that happens, fix it once with `docker compose -f dev/compose.yaml exec -T --user root kimai
chown -R www-data:www-data /opt/kimai/var/cache /opt/kimai/var/data`, then keep using
`--user www-data` from then on. `dev/reset.sh` already does this correctly.
Unit tests need no Kimai: `php tests/run.php`.

## Reference data

Exported reference timesheets were the source. The PDFs stay local (`reference/`, git-ignored: they carry a name and a signature). Their numbers live on as anonymized fixtures in `tests/fixtures/`, with dates shifted by 52 weeks. Analysis notes in `research/timesheet-app-analyse.md` (outside `docs/`, not published).

## Phases

### Phase 1 — Core

- [x] `agent.md`, `Design.md`, `roadmap.md`
- [x] Bundle skeleton (composer.json, bundle class, DI extension)
- [x] Value objects: ruleset, tiers, rounding, day input, day result
- [x] Presets: TV FFS 2024, "Quarter-Hour Ruleset"
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
- [x] Holiday plugin integration for public holidays. `DayInputBuilder` now asks a `HolidayLookupInterface`
      before falling back to the weekday; a film day override still always wins. Default implementation
      (`NullHolidayLookup`) says no to everything, so the plugin behaves exactly as before when no holiday
      plugin is installed. `DrehzettelExtension::configureHolidayLookup()` swaps in
      `Service\Holiday\HolidayBundleLookup` — reading the user's public holiday group and its holidays —
      only when `KimaiPlugin\HolidayBundle` (github.com/shrippen/kimai-holiday-bundle) is actually present,
      guarded by `class_exists()`. That adapter class is excluded from the plugin's normal service
      auto-discovery (`services.yaml`) so the container still compiles when the holiday plugin is absent.
      Not covered by `php tests/run.php` (needs Kimai entities); checked manually with `kimai:reload` +
      `dev/check.php` + `drehzettel:pdf` on the dev instance without the holiday plugin installed. Still
      needs a manual check with the holiday plugin actually installed once that's convenient to set up.
- [ ] Time account (AZV day) — deferred. A real accrual ledger (2.5 h + 30 min per consecutive shooting day,
      TV FFS §6), not a one-line addition; out of scope for this pass.

## Open

- `MailConfiguration` needs a "from" address configured in Kimai (system settings), otherwise
  `KimaiMailer` throws. Checked on the dev instance with the `null://` transport (no real send).
- "Begun hour" surcharge reading (TV FFS 5.4.3.2): default in TV FFS preset is round up.
- Under-time (`Unterstunden`): assumed to be the work time missing to 8 h on a shooting day. The source's info text could not be read, and no sample sheet has a day under 8 h with that column.
- Unverified against real PDFs (no example with these cases): weekly overtime above 50 h, 6th/7th day, Sunday/holiday pay, Saturday pay. The "Quarter-Hour Ruleset" preset copies the reference source's settings, but assumes: 6th/7th day surcharges stack with Saturday/Sunday surcharges; Sunday/holiday use the day rate.
- Half-cent ties: the reference source shows 578.125 as 578.12; the plugin rounds half up (578.13). Tests allow 1 cent there.
- Daily gage pays at least a full day (7:15 h -> 400.00 EUR in the daily-gage example). Weekly gage pays worked time.
- Timesheet entries of one date are merged into one span (earliest begin to latest end). Gaps between entries are not treated as break.

Resolved, previously listed here:

- ~~`dev/check.php`'s `period weeks` check fails (expects 1, gets 2)~~ — not a `FilmWeekService::period()`
  bug: `dev/seed.php` seeds *all* weeks of the `weekly_gage` reference fixture for the admin user (May
  through September 2025), not just week 21 as the check's comment assumed. Its query range (May 2 to
  June 2) legitimately covers two ISO weeks with entries (21 and 22), so getting 2 `WeekResult`s back was
  correct. Narrowed the check's range to week 21 only (`2025-05-19` to `2025-05-24`) to match its original
  intent.

- ~~Pause over 45 min ruleset option~~ — already implemented (`BreakRule::EXCESS_COUNTS_AS_WORK` +
  `freeBreakMinutes`, used by the TV FFS preset) and tested (`tests/cases/day.php`, "tv break excess is
  work"). This roadmap file just hadn't been updated to say so.
- ~~Rest-time compliance across a week boundary~~ — `ComplianceChecker::check()` now takes an optional
  `$previousDay` (the last shooting day before the current week, from `FilmWeekService::lastDayBefore()`)
  and checks rest time against it too. `WeekPageBuilder` wires it in. Tested in `tests/cases/compliance.php`.
- ~~Interplay with Holiday plugin target hours~~ — moot now that public holidays are detected automatically
  (see Phase 5): a holiday gets `DayCategory::HOLIDAY` and its surcharge like any other day, same as before
  for a hand-set film day override. Kimai's own "expected work hours" accounting is out of this plugin's
  scope either way.
- ~~Holidays are not detected yet~~ — see Phase 5, holiday plugin integration.
