# Design Reference

All visual design decisions for this project follow the shared [shrippen DesignDefault](https://github.com/shrippen/shrippen.github.io) design system.

## Quick Links

- **Full spec**: <https://github.com/shrippen/shrippen.github.io>
- **CSS tokens**: <https://github.com/shrippen/shrippen.github.io/blob/main/tokens/variables.css>
- **Landing page template**: <https://github.com/shrippen/shrippen.github.io/blob/main/templates/landing.html>

## Key Decisions

| Aspect | Choice |
|---|---|
| Palette | Gruvbox-inspired warm dark (`bg0: #282828`, `fg1: #ebdbb2`, accent cream `#e8dcc4`) |
| Headings font | [Rajdhani](https://fonts.google.com/specimen/Rajdhani) 600/700 |
| Body font | System sans stack |
| Code font | JetBrains Mono / Fira Code / Cascadia Code |
| Links / primary action | `--blue: #83a598` |
| Landing page layout | DesignDefault vertical rhythm: icon → name → tagline → badges → install card → CTA → features → prose → footer |
| Max content width | 860px |
| Badges | shields.io with `labelColor=1c1c20`, version `e8dcc4`, tech `83a598`, license `a89984` |
| No light mode | Dark-first only for landing pages |

## Inside Kimai

The pages inside Kimai follow the shared UI guidelines of [kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui) ([GUIDELINES.md](https://github.com/shrippen/kimai-plugin-ui/blob/main/GUIDELINES.md), [CHECKLIST.md](https://github.com/shrippen/kimai-plugin-ui/blob/main/CHECKLIST.md)). The kit lives in `Resources/views/_kit/` and `Resources/translations/kpu.*.xlf`; update it only with `bin/sync.sh` from that repo. The DesignDefault palette above is for the landing page, not for Kimai pages. Status of the migration: `UI-TODO.md`.

## Timesheet PDF

- A4 landscape, DejaVu Sans (ships with mPDF), black on white. Prints and faxes cleanly.
- No brand colors in the PDF. Production offices receive it, not the plugin's audience.
- Every column is optional (see `roadmap.md`). Layout must stay readable with any subset.
- Footer states the rounding rules used, so the production can verify the numbers.
- Signature lines: production management left-hand slot, crew member right-hand slot.

When making visual changes to the landing page (`docs/index.html`) or any future web-facing assets, consult the DesignDefault README for the full rules.

## Identity

- **Name**: Drehzettel. Bundle `DrehzettelBundle`, namespace `KimaiPlugin\DrehzettelBundle`, tables `kimai2_ext_drehzettel_*`, install command `kimai:bundle:drehzettel:install`.
- **Slug**: `kimai-drehzettel-bundle`.
- **Tagline**: Film crew timesheets for Kimai.
- **Logo**: "Film frame with check" (E from the logo studio). Landscape frame with perforation echoes the landscape PDF, the yellow check is the signed timesheet.
- **Files**: `docs/icon.svg` (cream + yellow accent), `docs/icon-mono.svg` (cream only), `docs/social-preview.png` (1280x640, same layout as `make-social.py`).
