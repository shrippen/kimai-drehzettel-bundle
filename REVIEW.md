# Review guide

Every PR is reviewed before it is merged (rule in `agent.md`): by PR-Agent
(owner, 22.10.2026); a new Claude agent checks the **Wichtig** of earlier
rounds, and reviews when PR-Agent fails. The
reviewer knows nothing of the session that wrote the change: it gets the
PR's intent, its diff and the repository, and this file. The owner does not read code; this review is the second reader.

## What failure looks like here

Drehzettel is a Kimai plugin for employed film crew: the shooting day is
tracked once in Kimai and turned into the timesheet the production office
asks for, by the TV FFS rules. A bug that matters is one that changes what
a crew member is paid or sends:

- **Wrong figures:** hours, breaks, daily and weekly overtime, night work,
  Saturday, Sunday and holiday surcharges, day 6 and 7, travel days, AZV
  credit or rounding computed wrongly; days across midnight, night shoots
  or time zones on the wrong day. The README cites the tariff points (TZ)
  behind the rules; a change that departs from them is a bug unless the
  README says why.
- **Wrong warnings:** working or rest time warnings that misfire or stay
  silent, or a warning that changes a figure.
- **Leaks:** controllers, API or exports without the role or ownership
  check they need, one user's days or engagements reaching another
  without `drehzettel_manage`, a mail sent to the wrong person.
- **Breaks clients:** the API v1 losing or renaming a key, a changed error
  code or status (clients such as the Plasmai widget rely on them).
- **Breaks Kimai:** a 500 on a page, migrations that do not match the
  entities.
- **Looks wrong:** own colours, sizes or copies of kit macros instead of
  Knust and the kit; text missing in German or English.
- **Ships the demo:** code or data from `demo/` in the release zip.
- **Lies:** README or comments that say something the code does not do.

## What to check in particular

- Calculation changes have a case in `tests/cases` (`php tests/run.php`).
- New translation keys exist in the German and English files in
  `Resources/translations/`.
- The kit in `Resources/views/_kit/` changes only by the kit's `sync.sh`.
- Entity changes come with a migration.
- Release: tag equal to the version in `composer.json`; a section
  `## [X.Y.Z] — date` in `CHANGELOG.md` with lead and groups in English and
  German (it becomes the release notes, the release workflow fails
  without it); the same entry at the top of `#changes` in `docs/index.html`
  and the footer version bumped (release rule in `agent.md`).
- The coding rules at the top of `agent.md` (early return, braces on every
  `if`, enums instead of boolean parameters, private members, layers).
- Bug fixes: does the fix remove the cause, with a test that failed
  before?

## What the owner confirms here

"What is published" (rule in `agent.md`) means here: a release (a tag that
GitHub builds into the plugin zip), a change to the release workflow, a new
public page under `docs/`.

## Skip

- `Resources/views/_kit/` (vendored kit), `research/`, `dev/`,
  screenshots and images.

## How to report

- Every finding names file and line and the concrete input or state that
  goes wrong. No guess without evidence; if unsure, say so.
- Each finding gets one label: **Wichtig** (a bug from the list above,
  must be fixed or answered before the merge), **Kleinigkeit** (optional,
  at most five) or **Bestand** (the bug was there before this PR; not
  blocking).
- The report starts with the count, e.g. "2 Wichtig, 1 Kleinigkeit" (the
  session puts the heading above it). The count of **Wichtig** holds every
  one still open, also from an earlier round, e.g. "1 Wichtig (offen aus
  Runde 1)". Only when none is open it says plainly: "Nichts zu beheben."
- In a later round: say for each open **Wichtig** of the last round whether
  it is fixed, or whether its answer is accepted, with the reason. PR-Agent
  sees no earlier round: it reports only its own findings with their labels,
  and a new Claude agent closes the open ones (`agent.md`).
- Findings in German, code names as they are.
