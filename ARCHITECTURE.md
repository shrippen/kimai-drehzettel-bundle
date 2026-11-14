# Architecture

How this repository is meant to be built. Where the code differs today is listed under
"Known deviations". Change this file in the PR that changes the structure.

## What it does

A Kimai plugin for employed film crew. A shooting day is tracked once as a normal Kimai
timesheet entry; the plugin adds the film day fields, calculates the day and the week by
the collective agreement (TV FFS) and produces the timesheet the production office asks
for, as a page, a PDF, a mail and an API for other programs.

## Building blocks

| Path | Its one task |
|---|---|
| `Enum/` | The fixed choices of the domain (day type, catering, rounding, mail rhythm). |
| `Domain/` | Values and rules without framework: ruleset, period, rounding, tiers, results, AZV, mail template and schedule, and the API's values (`Api*`, `DaySummary`, `FilmDayPatch`). |
| `Entity/` | What is stored: engagement, film day, custom ruleset, mail settings and log, signature. |
| `Repository/` | The queries for those entities and for Kimai's timesheet entries of a period. |
| `Migrations/` | One version file per schema change; `Service/SchemaStatus` finds pending ones. |
| `Service/` | Every use case. Calculation (`DayCalculator`, `WeekCalculator`, `PayCalculator`, `ComplianceChecker`), engagements and film days (`EngagementService`, `FilmDayService`, `FilmWeekService`, `EngagementAccess`), output (`WeekPageBuilder`, `TimesheetViewBuilder`, `PdfExporter`, `DaySummaryService`), mail (`MailComposer`, `TimesheetMailer`, `ScheduledMails`, `MailHistory`). |
| `Controller/` | The plugin's own pages: overview, week, engagement, ruleset, mail, signature. |
| `Controller/Api/` | The REST API for other programs (e.g. the Plasmai widget). |
| `Form/` | Symfony forms, and `TimesheetFormExtension`, which adds the film day fields to Kimai's own entry form. |
| `EventSubscriber/` | Hooks into Kimai: menu, permissions, page actions (`Actions/`), entry saved or deleted, theme, pending migrations, automatic mails. |
| `Command/` | Console commands: install, week, PDF, due mails. |
| `Resources/views/` | Templates: pages (`drehzettel/`), PDF (`pdf/`), mail (`mail/`). `_kit/` is the vendored Kimai plugin kit, never edited here. |
| `Resources/translations/` | All texts, German and English. |
| `tests/` | Dependency-free tests (`php tests/run.php`), one file per topic in `cases/`. |

## Layers

```
 entry points   Controller  Controller/Api  Command  EventSubscriber  Form
                     │  ask for a use case, show the answer
                     ▼
 use cases      Service ──────────────► Kimai  (only through TimesheetWriter,
                     │                          HolidayLookupInterface, Labels)
                     ▼
 storage        Repository
                     │
                     ▼
 model          Entity   Domain   Enum      (used by every layer above)
```

- An entry point calls services, never a plugin repository. It may use Kimai's own
  repositories only to turn an id from the request into a user, project or activity.
- An entry point holds no rule. Loading an engagement with its permission check, limits of
  a field, what a save does: all of that is a service or a domain value.
- `Domain/` and `Enum/` import nothing outside the plugin: no Symfony, no Doctrine, no
  Kimai. That keeps the calculation testable without Kimai.
- A calculation result is produced once, in a service. Pages, PDF, mail and API only
  format it.
- Whatever enters from a browser or an API client (form field, query value, JSON body) is
  read and limited in one `Domain/` value per input, shared by every entry point.
- Kimai is written to and read from behind an interface in `Service/`, so a Kimai change
  touches one class.

## Where new things go

| Change | Touch |
|---|---|
| A field on the film day | `Entity/FilmDay` and a migration; its limits once in `Domain/`; `Service/FilmDayService` and `DayInputBuilder`; the three inputs (`_week_table.html.twig`, `Form/TimesheetFormExtension`, `Domain/FilmDayPatch`); translations; a test in `tests/cases/`. |
| A tariff rule or surcharge | The rule as a `Domain/` value; its key in `RulesetCodec` and `RulesetFormMapper`; the calculator in `Service/`; `Form/RulesetType`; `tests/cases/tariff.php`. |
| A sum or column of the week | The service that calculates the week; then `WeekPageBuilder` and `TimesheetViewBuilder` format it; for the PDF also `Enum/PdfOption`. |
| A page | `Controller/`, a template in `Resources/views/drehzettel/`, `Service/PageSetups`, its actions in `EventSubscriber/Actions/`, the permission in `Service/EngagementAccess`. |
| An API endpoint or field | `Controller/Api/`, the answer as a `Domain/` value, a feature name in `Domain/ApiInfo`, errors through `Domain/ApiError`, the API section of `README.md`, `tests/cases/api.php`. |
| A mail option | `Enum/Mail*`, `Domain/MailSchedule` or `MailTemplate`, the mail services, `Form/MailSettingsType`. |
| A table or column | `Entity/`, `Repository/`, a new file in `Migrations/`. |
| A console command | `Command/`, which calls a service. |

A change that needs more places than its row lists is a finding for the next audit.

## Known deviations

- Controllers, subscribers and `TimesheetFormExtension` use plugin repositories directly
  (ten files); `WeekController` and `MailController` load the engagement with a copied
  permission check, and `MailController` sends the mail itself. `AUDIT.md` finding 6.
- The film day fields are read and limited separately by the week page, Kimai's form and
  the API; the note allows 10 000 characters in one and 500 in the other. Finding 3.
- The week's sums are calculated twice, in `WeekPageBuilder` and `TimesheetViewBuilder`.
  Finding 4.
- Currency, percent and cent conversions are repeated instead of living in `Domain/`.
  Finding 1.
- An unknown activity leaves the API as Symfony's error, not as an `ApiError`. Finding 2.
- `ThemeSubscriber` carries about 150 lines of JavaScript as a PHP string instead of a
  template. Finding 5.
