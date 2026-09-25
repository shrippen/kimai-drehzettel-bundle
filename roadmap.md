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

- [x] Entities: ruleset template, engagement, film day (break, catering, category, day type, production day, extra pay, note)
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
- [ ] Time account / AZV day — deferred. Two separate ledgers, source `research/tv-ffs-zeitkonto-und-folgetage.md`:
      (a) overtime time account, TV FFS Anlage Zeitkonto A.1.1 (hours > 50/week plus overtime surcharges as
      time; dissolved after production in 8 h days at 1/50 weekly fee, A.1.3);
      (b) AZV credit, TV FFS TZ 6.1-6.4 (was "§6" here, claim confirmed): crew behind the camera, shoots
      starting from 2025-05-01; 2.5 h after 5 consecutive full shooting days, then 0.5 h per further day,
      per block of 20 shooting days (= 10 h = one AZV day). Weekends and days off do NOT reset this
      counter, so it must not reuse the `consecutive` streak of the 6th/7th day.
- [ ] Ausgleichstage (TV FFS TZ 5.6.2) — not modelled (PO decision D-6, 2026-09-25); users note them in the
      day note for now. One paid rest day per worked Sunday (> 4 h on the Sunday for a shift over midnight)
      and per worked Christmas, Easter, Whit holiday, 3 Oct, 1 May; not for other holidays. ArbZG § 11:
      within 2 weeks (Sunday) / 8 weeks (weekday holiday). Weitere Recherche nötig (Anspruch, Einheit,
      Frist, Abgeltung), see the research file.
- [x] 6th/7th day by consecutive days (PO decisions D-1..D-5, 2026-09-25), built as ruleset option
      `streakMode` (`Enum/StreakMode`): `calendarWeek` (n-th entry of the ISO week, the former behaviour)
      or `consecutive` (days in a row across weeks, reset by a calendar day without entry, travel days
      count, day 8+ like day 7; `Service/ConsecutiveDayCounter` looks back in 14-day windows).
      `productionDay` is the override ("Zuschlagstag"): 1-7 / 1-999, in `consecutive` the following
      days continue from it. Badge next to the date, PDF label, day summary `consecutiveDay`,
      `consecutiveDayOverridden`, `streakMode`, ping feature `consecutiveDays`.
      **PO decision pending:** TV FFS TZ 5.4.3.1/5.4.3.4 count the 6th/7th day *of the calendar week*
      (`research/tv-ffs-zeitkonto-und-folgetage.md`), and `consecutive` can underpay: Mon, Tue, (Wed off),
      Thu-Sun makes Sunday day 6 by tariff but day 4 in a row. Until the PO confirms, `calendarWeek`
      stays the default for presets and stored rulesets; switching is the one line
      `StreakMode::DEFAULT`. Engagement snapshots and custom rulesets saved after this change carry
      their mode explicitly and do not follow a changed default.
      Rest time < 11 h stays a warning (D-7); the 11.5 h trigger stays measured on presence incl. break
      (D-8), although the employers' FAQ reads it as net work time.

### Phase 6 — Kimai form integration and external API

- [x] `Form/TimesheetFormExtension.php`: adds a "film day" toggle plus break/catering/category/note
      fields directly to Kimai's own timesheet entry form (`TimesheetEditForm` and
      `TimesheetAdminEditForm`), conditional on an active engagement for the entry's
      project+user+date (form concept A, `research/ux-flows-film-day-data.md`). Kimai's native
      `break` field is removed from the form builder in that case — verified in the rendered HTML
      that `timesheet_edit_form[break]` is absent while the four `drehzettel*` fields are present;
      no sync between the two break concepts, per the 2026-09-23 revision.
      Known limitation: the engagement lookup happens once, at form-build time, from the entry's
      existing project/user/begin — a brand-new entry without a project preset yet will not show
      the toggle even if the user picks a matching project afterwards. A live re-check via the new
      API is a follow-up, not part of this pass.
- [x] `Controller/Api/DrehzettelApiController.php`: versioned REST API for external clients (e.g.
      the Plasmai KDE Plasma widget) under `/api/drehzettel/...`, plan in
      `research/api-external-clients.md`. `GET /ping` (discovery), `GET /v1/engagement-status`,
      `GET`/`PUT /v1/film-days/{date}`. Reuses Kimai's own `/api` firewall, `#[IsGranted('API')]`
      voter and Nelmio OpenAPI doc scan — all three are matched on the route path, not restricted
      to Kimai's own `src/API/` directory, confirmed by reading Kimai 2.67.0's
      `ApiRequestMatcher`/`ApiVoter`/`nelmio_api_doc.yaml` directly.
      Checked end to end on the dev instance with a real session: `debug:router` lists all four
      routes correctly un-prefixed by `{_locale}`; unauthenticated calls correctly 401; an
      authenticated `PUT .../film-days/{date}` followed by `GET` round-trips break/catering/
      category/note through the real `FilmDay` table.
      Not yet built: permission nuance beyond "own data or `drehzettel_manage`" (no dedicated tests
      for the cross-user case), and the Plasmai-side consumption itself (separate project).
      **Field gap found 2026-09-24** while scoping that Plasmai-side work: `FilmDay` has `dayType`
      and `productionDay` columns (Entity, Phase 2), but `filmDayGet`/`filmDayPut` neither read nor
      write them — `GET` omits both from the response, and `PUT` hardcodes `DayType::WORKDAY` and
      `null` regardless of what the client sends. `extraPayCents` has no server-side field at all
      (Plasmai-local only, no equivalent in `FilmDay`). Until these are added, an external client
      can sync `breakMinutes`/`catering`/`category`/`note` through the API but must keep day
      type, production-day count and extra pay local-only.
      **Closed 2026-09-25** for `dayType`/`productionDay`: both are in `GET`/`PUT` now, and `PUT` is a
      partial update (only sent keys change) with 400 on invalid values. **Closed 2026-09-25** for
      `extraPayCents` too, see "Plasmai API additions" below. No field gap left.
- [x] Plasmai API additions (2026-09-25), documented in `README.md#api`; clients discover them via
      ping's `features`:
      - `GET /v1/engagements?date=&user=`: active engagements of a user on a date.
      - Film day `GET`/`PUT` also return `defaultBreakMinutes` (ruleset snapshot) and
        `effectiveCategory` (`DayInputBuilder::categoryFor()`, now public: weekday or public holiday).
      - Ping adds `permissions {view, manage}` and `features`.
      - Errors are `{error, code}`. Missing/malformed `project`, `user` or date is 400 (was 404);
        unknown ids and "no engagement" stay 404 (`no_engagement`); `engagement-status` keeps
        answering `active: false`. Another user's data without `drehzettel_manage` is 403 on
        film-days too (was 404 when that user had no engagement).
      - `extraPayCents` (Zusatzgage/Spesen, reference app "Tag-Erfassung"): column
        `extra_pay_cents` (migration `Version20260925000000`), added to the day's pay after the
        catering deduction, no surcharges. Week grid, timesheet form, PDF (below the day's pay), API.
      - `GET /v1/days/{date}/summary`: the day out of its calculated week, incl. `payCents`.
      - Two day numbers (product-owner decision D7, 2026-09-25; closes the former "Open" item on
        diverging `productionDay` meanings): `productionDay` stays the shooting day of the week
        (1-7, "Drehtag der Woche", drives the 6th/7th-day surcharge). New `shootingDayNumber`
        (1-999 or null, "Drehtag der Produktion", Plasmai's "Drehtag Nr. 37"): column
        `shooting_day_number` (migration `Version20260926000000`), informational only, no
        auto-computation. Week grid (badge next to the date), timesheet form, PDF (below the
        date), film day API, day summary, ping feature `shootingDayNumber`.
- [ ] Week view filter on the toggle state (still filters by engagement presence only) and an edit
      mode for the week view (Variante C's other half) — not part of this pass, see
      `research/ux-flows-film-day-data.md` "offen für die Umsetzung".

### Phase 6 follow-up — UI polish, requested 2026-09-23

- [x] Live feedback when picking a project on a *new* timesheet entry: `EventSubscriber\ThemeSubscriber`
      hooks Kimai's own `ThemeEvent::JAVASCRIPT`/`STYLESHEET` extension points (sitewide, no template
      override) to call the new API's `engagement-status` endpoint on the project field's `change`
      event and show an amber banner ("Drehtag erkannt — Regelwerk …") when it's active. Delegated on
      `document`, so it also survives the AJAX-loaded edit modal without extra wiring. Deliberately
      checks against *today*, not the entry's own date field (avoids parsing a locale-formatted date
      picker for what is only a convenience hint) — the authoritative, date-correct check still
      happens server-side in `TimesheetFormExtension` at save time. Checked live: selecting "Sample
      Film (dev)" on `/timesheet/create` now shows the banner within about a second.
- [x] Visual grouping for form concept A: the five fields from `TimesheetFormExtension` get
      `row_attr` classes (`dz-form-row` etc.), styled by `ThemeSubscriber`'s sitewide CSS into one
      amber-tinted, left-bordered block; the toggle and catering fields switched from a plain
      checkbox to Kimai's own `App\Form\Type\YesNoType` for the same pill-switch look as its
      "Exportiert" field. Checked live on `/timesheet/{id}/edit`: matches the workflow artifact's
      concept A closely enough to consider this resolved, not just "functionally correct."
- [x] Sidebar icon changed from generic `fas fa-film` to `fas fa-clapperboard`
      (`EventSubscriber/MenuSubscriber.php`) — closer to the clapperboard-with-checkmark mark in
      `docs/icon.svg`; Tabler/FontAwesome menu icons are icon-font classes only, so an exact match to
      the custom SVG isn't possible without a deeper theme override, judged not worth it here.
- [ ] Week view density: increased row padding (`.6rem 1rem` → `.9rem 1.25rem`), larger gaps and
      font sizes, and an explicit column-header row added to `week.html.twig` (previously none),
      styled to match Kimai's own muted-uppercase table headers. Checked live against
      `/timesheet/` and `/contract` for comparison. This narrows the density gap but is a targeted
      pass, not a full redesign onto a real Kimai-styled `<table>` — left open if more is wanted.
- [x] `/contract` ("Arbeitszeiten") integration: `EventSubscriber/ContractSubscriber.php` subscribes
      to core's `WorkingTimeYearEvent` and calls `App\WorkingTime\Model\Day::addAddon()` — the same
      per-day marker mechanism core itself uses for missing/unexpected-time cells — for every date
      with a saved `FilmDay` row, with `duration: 0` so it only marks the cell (`bg-drehzettel`,
      tooltip) and never changes the worked-time totals on that page. No template of ours involved;
      core's own `contract/status.html.twig` renders the marker. Re-checked live across the seeded
      `weekly_gage` reference data (May–September 2025, `kimai2_ext_drehzettel_day`): every one of
      the ~28 marked days shows the amber background correctly, not just the one synthetic test day
      from the first pass — there's no dedicated automated test since it needs Kimai's own
      `WorkingTime`/`Year` entities to exercise, only the manual dev-instance check.
- [x] Clapperboard icon on `/contract` marker cells: `DependencyInjection/DrehzettelExtension.php`
      now `prependExtensionConfig('tabler', ['icons' => ['drehzettel' => 'fas fa-clapperboard']])`.
      Without this, `App\WorkingTime\Model\DayAddon`'s type string is used as-is as a CSS class by
      Tabler's `icon()` Twig function when the key isn't registered (`vendor/kevinpapst/tabler-
      bundle/.../RuntimeExtension.php: $this->icons[$name] ?? $default ?? $name`) — renders nothing,
      no error. This is specific to `drehzettel` being a brand-new key: checked
      `KimaiPlugin\HolidayBundle` (`~/Hacking/eigene/Kimai Holiday Plugin`) does *not* have the same
      gap — every icon key its `AbsenceType::icon()`/`WorkingTimeYearSubscriber::PUBLIC_HOLIDAY_ICON`
      use (`holiday`, `sickness`, `time-off`, `other`, `public-holiday`) is already pre-registered in
      Kimai core's own `config/packages/tabler.yaml`, so it never needed a registration of its own.
      (An earlier note here claimed otherwise without checking core's existing keys — wrong, corrected
      2026-09-23.) Checked live: the clapperboard now renders at actual size inside marked cells.

All of the above verified against `kimai/kimai2:2.67.0` via `dev/compose.yaml` (Docker), a real
session, and `php tests/run.php` (261 checks, unaffected) — not just `php -l`.

## Open

- `MailConfiguration` needs a "from" address configured in Kimai (system settings), otherwise
  `KimaiMailer` throws. Checked on the dev instance with the `null://` transport (no real send).
- "Begun hour" surcharge reading (TV FFS 5.4.3.2): default in TV FFS preset is round up.
- Under-time (`Unterstunden`): assumed to be the work time missing to 8 h on a shooting day. The source's info text could not be read, and no sample sheet has a day under 8 h with that column.
- Unverified against real PDFs (no example with these cases): weekly overtime above 50 h, 6th/7th day, Sunday/holiday pay, Saturday pay. The "Quarter-Hour Ruleset" preset copies the reference source's settings, but assumes: 6th/7th day surcharges stack with Saturday/Sunday surcharges; Sunday/holiday use the day rate.
- Half-cent ties: the reference source shows 578.125 as 578.12; the plugin rounds half up (578.13). Tests allow 1 cent there.
- Daily gage pays at least a full day (7:15 h -> 400.00 EUR in the daily-gage example). Weekly gage pays worked time.
- Timesheet entries of one date are merged into one span (earliest begin to latest end). Gaps between entries are not treated as break.
- **UX question raised while reworking the week/rules pages (see `research/ux-flows-film-day-data.md`,
  2026-09-22) — decided and partly built (Phase 6, 2026-09-23):** film day data now also lives in Kimai's
  own timesheet entry form (one save for `Timesheet` + `FilmDay`, form concept A), not only in the week
  view's inline dropdowns. Still open: the new-entry limitation where the toggle needs a project already
  picked to appear.
- New-engagement view: the User, Project and Ruleset fields should be searchable (select with
  filter-as-you-type) instead of plain dropdowns — noted while testing in production, where the user
  and project lists are long enough that scrolling a plain `<select>` is impractical.
- Generate activity presets from TV FFS (Tätigkeitsbezeichnung plus Wochengage) — noted while testing
  in production.

## Phase 6 follow-up 2 — week view alignment + per-row edit mode, rules page contrast fix (2026-09-23)

After the first UI polish pass the user was still unhappy with the week view and flagged the rules
page as visually inconsistent too. Two Artifacts with 3 design directions each were reviewed, plus a
reference mockup the user linked separately; the user picked, in their own words: for the week view "a
combination of the current list and Concept C [compact list + detail] with a per-row edit mode", and
named the core problem as row values being hard to match to the column labels above; for the rules
page, keep the icon language and just repair the current layout (not a redesign).

Implemented, verified live against the dev instance in both cases:

- `Resources/views/drehzettel/week.html.twig` / `_week_table.html.twig`: the day-list header and every
  row now share one CSS Grid `grid-template-columns` (fixed/minmax tracks) instead of independently-sized
  flex items. Previously each row's `.dz-day-tiers`/`.dz-day-actions` cell sized itself to its own content
  (chip text length, select option length), so columns silently drifted out of alignment with the header
  from row to row - that was the "hard to match to the columns above" complaint. Grid tracks fix this
  structurally: a cell's content can wrap or vary in width without moving any other column.
- Added a per-row edit mode: editable fields (break, catering, day type, category, note) render as a
  compact read-only summary by default (`.dz-view`) and a pencil button next to the row swaps in the
  actual form controls (`.dz-edit`) for that row only, via a delegated click listener (rows are
  re-rendered by the existing live-preview fetch, so per-button listeners would not survive). The open
  row's key is tracked in JS and re-applied after each preview refresh, so typing in a field does not
  collapse the row back to display mode. Computed/non-editable columns (date, work, tiers, night/under,
  pay) are never wrapped in view/edit toggles - they are always plain text, addressing the "display vs.
  input not visually distinguished" issue from the design review.
- `Resources/views/drehzettel/ruleset_form.html.twig`: fixed the genuine dark-mode contrast bug found
  during the design review - `.dz-cat-saturday`/`-sunday`/`-holiday` used hardcoded light hex backgrounds
  (`#fef3e0`/`#fbeaea`/`#efe9fc`) with no theme awareness, unlike every other color in that file. Swapped
  to Tabler's own `--tblr-warning-lt`/`--tblr-danger-lt`/`--tblr-purple-lt` (confirmed present in Kimai
  2.67.0's built CSS and already dark-mode aware via `color-mix`), so the cards now render as muted,
  legible tinted panels in both themes instead of blowing out to bright pastel in dark mode.

No other part of the rules page was restructured (icon language and card layout were explicitly kept as
they are) and no PHP/controller changes were needed for either fix - both are template/CSS/JS only.

## Phase 6 follow-up 3 — rules page custom-field checkbox removed, week view spacing/contrast (2026-09-23)

Four more points from a live pass over both pages:

- **Rules page, "Benutzerdefiniert" checkboxes removed entirely** (user: they are pointless once
  Erweitert exists - in that mode every field is a free field by definition, so there is nothing left
  for a per-field checkbox to decide). `_preset_field.html.twig` no longer renders the checkbox; the
  page-wide Einfach/Erweitert switch now drives every field's select/input pair directly
  (`applyFieldMode()` in `ruleset_form.html.twig`), and a ruleset saved with an out-of-preset value still
  opens in Erweitert automatically so the value stays visible. This replaces the previous session's fix,
  which had only hidden the checkboxes in Einfach mode - a step short of what was actually wanted.
- **Ladder bar contrast (`Tägliche`/`Wöchentliche Überstundenstufen`)**: the `+25%` (amber `#f2b53d`) and
  `+50%` (orange `#e8770f`) segments used white text like the rest of the bar, but at ~1.9:1 and ~3.0:1
  that fails WCAG's 4.5:1 for text that size - genuinely hard to read, not just a design preference.
  Switched those two segments to dark text (`#4a3200` / `#3d1f00`); the `+100%` segment's dark red already
  had enough contrast with white text and was left as-is.
- **Samstag/Sonntag/Feiertag cards**: re-checked after the checkbox removal - no further issues found,
  the `--tblr-*-lt` fix from the previous pass still holds in dark mode.
- **Week view spacing**: the grid columns added in the previous pass were still capped too low, so the
  Zuschläge (tier chip) and Catering/Tagestyp/Kategorie cells wrapped onto 2-3 lines well before the row
  ran out of width. Fixed by (a) giving the tier chips a fixed 2-column mini-grid instead of unpredictable
  flex-wrap, so they never collapse past 2 lines regardless of width, (b) only showing catering/day-type/
  category text in the compact view when it differs from the default (workday, no catering, auto
  category) instead of spelling out "Arbeitstag · Automatisch (Wochentag)" on every single row, and
  (c) reflowing the column tracks (wider tiers/actions minimums, tighter gap) to use the freed-up space.
  Also wrapped the day-list header and rows in one `.dz-day-scroll` (`overflow-x:auto`) container instead
  of two separate elements, so on a narrow viewport the whole list scrolls horizontally as one unit with
  its own scrollbar rather than either squeezing unreadable or breaking the page's own width - verified
  via a forced-narrow-container check (JS) that the list gets its own scrollbar while
  `document.body.scrollWidth` stays unchanged, i.e. no page-wide overflow.
- **The -9,50 € test entry**: not a bug. `API-Testeintrag` on 23 Sept. is a 2-minute shift (12:56-12:58)
  with a 30 min break, so `DayCalculator` correctly nets 0 work minutes (break is capped to gross minutes,
  same-length here). With 0 minutes there is no wage, but the entry also has Catering = yes, and
  `PayCalculator::dayCents()` deducts the engagement's fixed catering amount (9,50 €) unconditionally
  once catering is set, regardless of hours worked. 0 € wage minus a 9,50 € catering deduction is the
  -9,50 € shown. Confirmed by reading `Service/PayCalculator.php` and `Service/DayCalculator.php`; no
  code change made, this is a quirk of the specific (very short) API test fixture, not the calculation.

## Phase 6 follow-up 4 — sticky rules toolbar, week polish, night/under mislabel (2026-09-23)

Another round after a closer look at both pages plus direct timesheet creation:

- **Ladder base segment invisible in dark mode**: `.dz-ladder-base` used `--tblr-bg-surface-tertiary`
  (~rgb(38,38,38) in dark mode), almost the same as the card/page background it sits on, so only its text
  ("0–10") was visible - not a rendering bug, a near-zero-contrast one (confirmed by reading the element's
  actual computed background via JS: it was painting correctly, just indistinguishable from its
  surroundings). Switched to a fixed `--tblr-secondary` (#6b7280) grey with white text, which reads clearly
  against both light and dark card backgrounds, matching how the tier segments already use fixed solid
  colors rather than theme-following ones.
- **Pausenregel dropdown height mismatch**: it was missing `form-select-sm`, which the flanking preset
  fields' selects already use - added it so all three controls in the Pause row line up.
- **Erweitert hint no longer reflows the page**: it was a block-level element below the header, so
  showing it pushed every card down. Moved it into the same flex row as the mode toggle/Speichern button
  (a fellow row item, not a new row) and hid it below 1200px via `@media` with `!important` so it can't
  fight the JS-set inline `display`. It no longer affects layout at any width, and disappears outright
  once the row would be too narrow to hold it.
- **"Schließen" link removed** from the bottom of the rules form (redundant with the browser's own back
  navigation and the sidebar).
- **Rules page toolbar (title, Einfach/Erweitert, Speichern) made sticky** (`position:sticky;top:0`) so it
  stays reachable while scrolled through a long ruleset; the section nav's own sticky offset was bumped
  from `1rem` to `4.5rem` so it no longer sits underneath the now-sticky toolbar.
- **Week view "Arbeitszeit" column**: was 56px, tight enough that both the header word and the time value
  wrapped. Widened to 84px and added `white-space:nowrap` to both.
- **Week view Speichern button**: moved out of `#drehzettel-form` (via `form="drehzettel-form"`, the same
  cross-form-submit pattern the rules page's Speichern already used) into the same row as the "mail this
  week" form, right-aligned - was previously its own left-aligned line below the card.
- **"action.back" button removed** from the week view's top-right action group (redundant, same reasoning
  as the rules page's Schließen link).
- **"Nachtarbeit" column was mislabeled, not miscalculated**: the user saw the API-Testeintrag-adjacent
  entries report 2:45/3:30/4:45 "Nachtarbeit" for shifts entirely between 14:3x and 20:3x, nowhere near the
  22:00-06:00 night window. Traced through `DayCalculator::nightMinutes()` by hand for those exact
  begin/end/ruleset values - it correctly returns 0. The chip actually shown was `.dz-chip-under`
  (Unterstunden, the shortfall under `minDayHours`, amber with a warning-triangle icon), not
  `.dz-chip-night` (blue, moon icon) - both chips share the one `.dz-day-extra` column from the previous
  redesign pass, but the header only ever said "Nachtarbeit". Relabeled the header to "Nachtarbeit /
  Unterstunden" so the column matches what it can actually contain. No calculation changed.
- **Week view break text now distinguishes a stored 0/45/etc. value from the ruleset's default**: e.g.
  "Pause 45 min" for a day with nothing saved was reading as if 45 min had been explicitly entered. It's
  actually `film.break ?? v.default_break` - the ruleset's default, shown as a convenience. Now suffixed
  with "(Standard, nicht gespeichert)" when there is no stored value, so it reads differently from a day
  that really has 45 min saved.
- **Timesheet edit modal showing an unexpected Pause/Catering/Kategorie for a brand-new entry - not a
  bug**: reproduced live by creating a second timesheet entry on 23.09.2026, a date that already had a
  FilmDay row (break 30, catering yes, note "API-Testeintrag") from an earlier entry that same day. The
  edit modal correctly showed that day's existing FilmDay data, because `FilmDay` is one row per calendar
  day (by design - a shooting day is the union of every timesheet entry on it, see `DayInputBuilder`), not
  one row per timesheet entry. Two entries on the same date share one FilmDay row on purpose. This fully
  explains "Catering manchmal nicht angehakt" and "Samstag markiert obwohl Mittwoch": both only happen when
  a day already carries an override from an earlier entry or edit, and a later entry on that *same* date
  picks it up. To clear it, edit any entry on that date and reset the field (Kategorie back to
  "Automatisch", Catering off) - it applies to the whole day, not just the one entry being edited. No code
  change made; flagging here in case the per-day sharing model itself should change later, which would be
  a deliberate design decision, not a bug fix.
- **Inline per-row edit toggle**: could not reproduce "funktioniert nicht" after a fresh `cache:clear` -
  tested opening and closing edit mode on two different rows live, including through a live-preview
  refresh cycle, and the pencil/checkmark toggle worked correctly both times. Possibly a stale-cache
  symptom from before this session's edits were deployed.

## Phase 6 follow-up 5 — sticky toolbar margins, tier-row color linking (2026-09-23)

- **Sticky rules toolbar had no horizontal margin**: `.dz-topbar` used `padding:.75rem 0`, so once it had
  a solid background (needed for the sticky effect to read correctly while scrolled) its title/buttons sat
  flush against its own box edges - the page's normal 16px container gutter was still there, but with no
  padding of its own the toolbar box read as edge-to-edge compared to the padded `.dz-card`s below it.
  Fixed with the standard "bleed and reinset" trick: `padding:.75rem 1.5rem` together with
  `margin:0 -1.5rem`, so the background extends slightly wider than before (a proper toolbar bleed) while
  the title/button positions stay exactly where they were.
- **Erweitert-Hinweis breakpoint re-verified**: `resize_window` refused to resize the dev browser window at
  any size in this environment ("Bounds must be at least 50% within visible screen space", even for
  larger-than-current targets - an environment/tool issue, not something in the page). Verified the
  underlying mechanism instead: with the hint visible (Erweitert, inline `display:''`), injected a
  `!important` rule equivalent to what `@media (max-width:1199.98px)` applies once it matches, confirmed
  it correctly hides the hint over the inline style, and that removing the rule restores it. `@media` itself
  is standard, well-supported CSS, so this confirms the show/hide behavior is correct at any width.
- **Tier settings not visually linked to their ladder-bar segment**: the "Tägliche/Wöchentliche
  Überstundenstufen" ladder bars use fixed colors per tier (amber/orange/red), but the Stufe 1-4 fields
  below had no visual tie to which segment they configure. Added a `.dz-tier-row` wrapper per tier with a
  3px colored left border matching that tier's ladder color, so the eye can trace a field row back to its
  segment in the bar above. (First attempt put the border directly on the Bootstrap `.row` div - invisible,
  because `.row`'s own negative side margins pushed the border outside the card's padding box; fixed by
  wrapping the `.row` in a plain div that owns the border and padding instead.)

## Phase 6 follow-up 6 — topbar hierarchy, hint breakpoint, checkmark now saves (2026-09-23)

- **Sticky rules toolbar redesigned as its own inset card**: the previous bleed made it read as *more*
  full-width than Kimai's own top menu bar, the opposite of the wanted hierarchy. Replaced the bleed with
  a narrower floating card: `top:.75rem` (small gap above it instead of flush with the viewport edge),
  `border` + `border-radius` + a subtle `box-shadow`, `padding:1.5rem` with no compensating negative
  margin (so the box is genuinely narrower than the content column, and the heading/buttons sit indented
  inside it rather than at its edges). `.dz-nav`'s sticky offset bumped from `4.5rem` to `6rem` to clear
  the now-taller card.
- **Erweitert-Hinweis breakpoint**: raised twice on direct feedback - first to 1260px after the user found
  wrap/layout jank in the 1200-1247px window (the toggle+button group needs more room to stay on one line
  than the hint's own text width alone suggested), then to 1300px for extra headroom.
- **Per-row checkmark now actually saves, not just collapses**: previously it only removed the `.dz-editing`
  CSS class - the row's field values were always part of `#drehzettel-form` regardless of edit/view display
  (nothing was ever lost), but nothing was sent to the server either, which read as "did this even do
  anything?". There is no per-day save endpoint - `WeekController::save()` only ever persists the whole
  week's `day[...]` fields in one POST - so the honest fix is for the checkmark to call
  `form.requestSubmit()` (the same submission the big Speichern button triggers), not a new endpoint. This
  also answers the "offer to save multiple open editors" alternative the user proposed: there is no
  partial-save state to reconcile, since any save (checkmark or the big button) already persists every row
  at once, including any others still open for editing. Verified live: typed a note in an open row, clicked
  its checkmark, page navigated to `.../save` and back with the note persisted and the row collapsed.
- **"API-Testeintrag" note appearing on a brand-new entry**: same per-calendar-day `FilmDay` sharing
  explained in follow-up 4 (Catering/Kategorie) - the note field is just another column on that same
  shared row, so a new entry on a date that already has film-day data picks up its note too. Not a bug.

## Phase 6 follow-up 7 — interactive, validating tier ladder (2026-09-23)

The daily/weekly overtime ladder bar was purely decorative server-rendered output; a page reload was the
only way to see it reflect an edited Stufe. Three explicit asks: make it live, make Speichern (and the bar
itself) reject a Stufe order contradiction instead of accepting it, and make removing a Stufe shrink the
bar immediately rather than after a reload.

- **Live rebuild**: `_tier_ladder.html.twig`'s macro now always renders its wrapper `<div id="...">` (even
  with zero tiers, so there is always a DOM anchor), still server-rendered once for the initial paint. A
  new script in `ruleset_form.html.twig` (`rebuildLadder`/`rebuildAll`, exposed as `window.dzRebuildLadders`)
  owns it from then on: a delegated `input`/`change` listener on the form matches any
  `dailyTier\dAfter|Percent` / `weeklyTier\dAfter|Percent` field name and rebuilds that ladder's bar and
  marks from the current values (reading whichever of the preset select/custom input pair is currently the
  enabled one, so it also stays correct across an Einfach/Erweitert switch - `setMode()` now calls
  `dzRebuildLadders()` too).
- **Validation, client and server**: a later Stufe must start strictly after the previous *filled* one, in
  slot order (gaps from an unused slot are fine, just skipped). Client-side: on a violation the bar is
  cleared and replaced with a red warning naming both conflicting Stufen, both their "ab Std." fields get
  Bootstrap's `.is-invalid` treatment, and the toolbar's Speichern button (`button[form="dz-rules-form"]`)
  is disabled with an explanatory `title`; the form's `submit` handler re-checks and calls
  `preventDefault()` regardless, in case the button state is ever stale. Server-side, since JS can be
  bypassed: `RulesetFormMapper::tiers()` now throws a `\DomainException` with the same "Stufe X (ab Y Std.)
  muss später beginnen als Stufe Z (ab W Std.)" wording on the same violation - both `RulesetController` and
  `EngagementController` already wrap `fromForm()` in try/catch and flash the message without saving, so no
  controller changes were needed. Without this, `Ruleset`'s constructor silently `usort()`s tiers by
  `afterMinutes`, which would have quietly detached each percentage from the "Stufe N" label the user set
  it under, rather than telling them their input didn't make sense. `php tests/run.php` still passes (261
  checks) after the change.
- **Dynamic segment count**: since the bar is now fully owned by JS after load, clearing a Stufe's "ab Std."
  field removes its segment immediately (verified live - a 3-segment bar went to 2 segments on the same
  input event, no reload).

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
