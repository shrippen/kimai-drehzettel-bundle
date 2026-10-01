# Design Reference

## Landing page

The landing page (`docs/index.html`) and other web-facing assets outside Kimai are generated from
**Kante**, the shared shrippen design system: <https://github.com/shrippen/Kante> (checkout `../Kante`).
The page links `https://shrippen.github.io/v1/shrippen.css` and `shrippen.js`, follows Kante's
`templates/landing.html` and uses Kante's roles only (`--fg1`, `--primary`, `--link` …), never `#hex`.
Badges, layout and the dark-only rule for landing pages are in Kante's `README.md`; missing elements
go to Kante first. Kante does **not** apply to the pages inside Kimai.

## Inside Kimai

The pages inside Kimai follow the shared UI guidelines of [kimai-plugin-ui](https://github.com/shrippen/Kante/tree/main/kimai/kit) ([GUIDELINES.md](https://github.com/shrippen/Kante/blob/main/kimai/kit/GUIDELINES.md), [CHECKLIST.md](https://github.com/shrippen/Kante/blob/main/kimai/kit/CHECKLIST.md)). The kit lives in `Resources/views/_kit/` and `Resources/translations/kpu.*.xlf`; update it only with `kimai/kit/bin/sync.sh` from the Kante repo. The look comes from [Knust](https://github.com/shrippen/Kante/tree/main/kimai/knust). Status of the migration: `UI-TODO.md`.

## Timesheet PDF

- A4 landscape, DejaVu Sans (ships with mPDF), black on white. Prints and faxes cleanly.
- No brand colors in the PDF. Production offices receive it, not the plugin's audience.
- Every column is optional (see `roadmap.md`). Layout must stay readable with any subset.
- Footer states the rounding rules used, so the production can verify the numbers.
- Signature lines: production management left-hand slot, crew member right-hand slot.

## Identity

- **Name**: Drehzettel. Bundle `DrehzettelBundle`, namespace `KimaiPlugin\DrehzettelBundle`, tables `kimai2_ext_drehzettel_*`, install command `kimai:bundle:drehzettel:install`.
- **Slug**: `kimai-drehzettel-bundle`.
- **Tagline**: Film crew timesheets for Kimai.
- **Logo**: "Film frame with check" (E from the logo studio). Landscape frame with perforation echoes the landscape PDF, the yellow check is the signed timesheet.
- **Files**: `docs/icon.svg` (cream + yellow accent), `docs/icon-mono.svg` (cream only), `docs/social-preview.png` (1280x640, same layout as `make-social.py`).
