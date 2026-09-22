#!/usr/bin/env python3
"""Writes Resources/translations/messages.{en,de}.xlf from the tables below.

One table row = key, English, German. Edit here, then run: python3 dev/translations.py
Placeholders (%name%) must stay identical in both languages.
"""
from pathlib import Path
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "Resources" / "translations"

ROWS = [
    # PDF
    ("drehzettel.pdf.title", "Timesheet", "Arbeitszeitnachweis"),
    ("drehzettel.pdf.period", "Period:", "Zeitraum:"),
    ("drehzettel.pdf.name_line", "Name: %value%", "Name: %value%"),
    ("drehzettel.pdf.project_line", "Project: %value%", "Projekt: %value%"),
    ("drehzettel.pdf.role_line", "Role: %value%", "Tätigkeit: %value%"),
    ("drehzettel.pdf.name", "Name", "Name"),
    ("drehzettel.pdf.project", "Project", "Projekt"),
    ("drehzettel.pdf.role", "Role", "Tätigkeit"),
    ("drehzettel.pdf.date", "Date", "Datum"),
    ("drehzettel.pdf.begin", "Begin", "Beginn"),
    ("drehzettel.pdf.end", "End", "Ende"),
    ("drehzettel.pdf.break", "Break", "Pause"),
    ("drehzettel.pdf.work", "Work time", "Arbeitszeit"),
    ("drehzettel.pdf.night", "Night work", "Nachtarbeit"),
    ("drehzettel.pdf.under", "Under-time", "Unterstunden"),
    ("drehzettel.pdf.catering", "Catering", "Catering"),
    ("drehzettel.pdf.day_type", "Day type", "Tagestyp"),
    ("drehzettel.pdf.pay", "Pay", "Gage"),
    ("drehzettel.pdf.notes", "Remarks", "Anmerkungen"),
    ("drehzettel.pdf.production", "Production management", "Produktionsleitung"),
    ("drehzettel.pdf.crew", "Crew member", "Filmschaffende/r"),
    ("drehzettel.pdf.total", "Total", "Gesamt"),
    ("drehzettel.pdf.week", "Week", "Woche"),
    ("drehzettel.pdf.yes", "Yes", "Ja"),
    ("drehzettel.pdf.no", "No", "Nein"),
    ("drehzettel.pdf.weekly_overtime", "Weekly overtime: %parts%", "Wochenüberstunden: %parts%"),
    ("drehzettel.pdf.weekly_part", "%time% at %percent%", "%time% zu %percent%"),
    ("drehzettel.pdf.rounding", "Rounding: work time %work%, surcharges %surcharge%.", "Rundung: Arbeitszeit %work%, Zuschläge %surcharge%."),
    ("drehzettel.rounding.exact", "to the minute", "minutengenau"),
    ("drehzettel.rounding.rule", "%unit% (%mode%)", "%unit% (%mode%)"),
    ("drehzettel.rounding.unit.1", "1 min", "1 Min."),
    ("drehzettel.rounding.unit.15", "15 min", "15 Min."),
    ("drehzettel.rounding.unit.30", "30 min", "30 Min."),
    ("drehzettel.rounding.unit.60", "1 hour", "1 Std."),
    ("drehzettel.rounding.mode.up", "rounded up", "aufgerundet"),
    ("drehzettel.rounding.mode.down", "rounded down", "abgerundet"),
    ("drehzettel.rounding.mode.nearest", "to the nearest", "kaufmännisch"),
    ("drehzettel.day_type.title", "Day type", "Tagestyp"),
    ("drehzettel.day_type.workday", "Workday", "Arbeitstag"),
    ("drehzettel.day_type.travel", "Travel day", "Reisetag"),
    # Menu and pages
    ("drehzettel.menu", "Drehzettel", "Drehzettel"),
    ("drehzettel.overview.empty", "No engagements yet.", "Noch keine Engagements."),
    ("drehzettel.user", "User", "Nutzer"),
    ("drehzettel.project", "Project", "Projekt"),
    ("drehzettel.role", "Role", "Tätigkeit"),
    ("drehzettel.ruleset", "Ruleset", "Regelwerk"),
    ("drehzettel.valid_from", "Valid from", "Gültig ab"),
    ("drehzettel.valid_to", "Valid to", "Gültig bis"),
    ("drehzettel.valid_to_help", "Empty means open-ended.", "Leer bedeutet unbefristet."),
    ("drehzettel.open", "Open", "Öffnen"),
    ("drehzettel.signature", "Signature", "Unterschrift"),
    ("drehzettel.signature.help", "Stored once, used on every timesheet PDF you sign.", "Einmal hinterlegt, auf jedem Arbeitszeitnachweis, den du unterschreibst."),
    ("drehzettel.signature.stored", "A signature is stored.", "Eine Unterschrift ist hinterlegt."),
    ("drehzettel.signature.file_help", "PNG or JPEG, up to 512 KB.", "PNG oder JPEG, bis 512 KB."),
    ("drehzettel.rulesets", "Rulesets", "Regelwerke"),
    ("drehzettel.ruleset.builtin", "Built-in", "Eingebaut"),
    ("drehzettel.ruleset.custom", "Custom", "Eigene"),
    ("drehzettel.ruleset.none", "No custom rulesets yet.", "Noch keine eigenen Regelwerke."),
    ("drehzettel.ruleset.copy", "Copy", "Kopieren"),
    ("drehzettel.ruleset.new", "New ruleset", "Neues Regelwerk"),
    ("drehzettel.ruleset.name", "Name", "Name"),
    ("drehzettel.ruleset.delete_confirm", "Delete this ruleset?", "Dieses Regelwerk löschen?"),
    ("drehzettel.engagement.new", "New engagement", "Neues Engagement"),
    ("drehzettel.pay_kind", "Pay", "Vergütungsart"),
    ("drehzettel.pay_kind.weekly", "Weekly gage", "Wochengage"),
    ("drehzettel.pay_kind.daily", "Daily gage", "Tagesgage"),
    ("drehzettel.gage", "Gage (€)", "Gage (€)"),
    ("drehzettel.catering_deduction", "Catering deduction (€)", "Catering-Abzug (€)"),
    ("drehzettel.rules.edit", "Edit rules", "Regeln bearbeiten"),
    ("drehzettel.rules.break", "Break", "Pause"),
    ("drehzettel.rules.default_break", "Default break (min)", "Standard-Pause (Min.)"),
    ("drehzettel.rules.break_rule", "Break rule", "Pausenregel"),
    ("drehzettel.rules.break_rule.deduct_all", "Deduct the whole break", "Ganze Pause abziehen"),
    ("drehzettel.rules.break_rule.excess_counts_as_work", "Excess counts as work", "Überschuss zählt als Arbeit"),
    ("drehzettel.rules.free_break", "Unpaid up to (min)", "Unbezahlt bis (Min.)"),
    ("drehzettel.rules.rounding", "Rounding", "Rundung"),
    ("drehzettel.rules.work_rounding", "Work time", "Arbeitszeit"),
    ("drehzettel.rules.surcharge_rounding", "Surcharges", "Zuschläge"),
    ("drehzettel.rules.daily_tiers", "Daily overtime tiers", "Tägliche Überstundenstufen"),
    ("drehzettel.rules.weekly_tiers", "Weekly overtime tiers", "Wöchentliche Überstundenstufen"),
    ("drehzettel.rules.tier", "Tier", "Stufe"),
    ("drehzettel.rules.after_h", "after h", "ab Std."),
    ("drehzettel.rules.weekly_gage_hours", "Weekly gage hours", "Wochengage-Stunden"),
    ("drehzettel.rules.daily_gage_hours", "Daily gage hours", "Tagesgage-Stunden"),
    ("drehzettel.rules.min_day_hours", "Minimum paid day (h)", "Mindest-Tagesstunden"),
    ("drehzettel.rules.night", "Night work", "Nachtarbeit"),
    ("drehzettel.rules.night_from", "From", "Von"),
    ("drehzettel.rules.night_to", "To", "Bis"),
    ("drehzettel.rules.night_percent", "Surcharge %", "Zuschlag %"),
    ("drehzettel.rules.category", "Saturday, Sunday, holiday", "Samstag, Sonntag, Feiertag"),
    ("drehzettel.rules.basis", "Basis", "Basis"),
    ("drehzettel.rules.basis.hourly", "Hourly rate", "Stundensatz"),
    ("drehzettel.rules.basis.day_rate", "Day rate", "Tagessatz"),
    ("drehzettel.rules.week_count", "6th and 7th day", "6. und 7. Tag"),
    ("drehzettel.rules.week_count_help", "Empty pools that day into weekly overtime instead.", "Leer lässt den Tag in die Wochenüberstunden einfließen."),
    ("drehzettel.week.iso", "CW", "KW"),
    ("drehzettel.week.no_entry", "No timesheet entry", "Kein Zeiteintrag"),
    ("drehzettel.note", "Note", "Anmerkung"),
    ("drehzettel.category.title", "Category", "Kategorie"),
    ("drehzettel.category.auto", "Auto (weekday)", "Automatisch (Wochentag)"),
    ("drehzettel.category.workday", "Workday", "Werktag"),
    ("drehzettel.category.saturday", "Saturday", "Samstag"),
    ("drehzettel.category.sunday", "Sunday", "Sonntag"),
    ("drehzettel.category.holiday", "Holiday", "Feiertag"),
    ("drehzettel.catering.title", "Catering", "Catering"),
    ("drehzettel.pdf.month", "Month PDF", "Monats-PDF"),
    ("drehzettel.compliance.title", "Working time limits", "Arbeitszeitgrenzen"),
    ("drehzettel.compliance.daily_max", "%date%: %measured% h is more than the 12 h daily maximum.", "%date%: %measured% Std. übersteigen die tägliche Höchstarbeitszeit von 12 Std."),
    ("drehzettel.compliance.weekly_max", "Week of %date%: %measured% h is more than the 60 h weekly maximum.", "Woche ab %date%: %measured% Std. übersteigen die wöchentliche Höchstarbeitszeit von 60 Std."),
    ("drehzettel.compliance.rest_time", "%date%: only %measured% h of rest before this day, %limit% h are required.", "%date%: nur %measured% Std. Ruhezeit vor diesem Tag, nötig sind %limit% Std."),
    ("drehzettel.mail.to", "Mail this week's PDF to", "Diese Woche als PDF mailen an"),
    ("drehzettel.mail.send", "Send", "Senden"),
    ("drehzettel.rules.custom", "Custom", "Benutzerdefiniert"),
    ("drehzettel.rules.unused", "Not used", "Nicht genutzt"),
    ("drehzettel.rules.pooled", "Pooled into weekly overtime", "Fließt in Wochenüberstunden"),
    ("drehzettel.rules.surcharge", "Surcharge", "Zuschlag"),
]


def unit(key, source, target, lang, index):
    text = source if lang == "en" else target
    return (f'      <trans-unit id="d{index:03d}" resname="{key}">\n'
            f'        <source>{escape(key)}</source>\n'
            f'        <target>{escape(text)}</target>\n'
            f'      </trans-unit>\n')


def write(lang):
    body = "".join(unit(k, en, de, lang, i) for i, (k, en, de) in enumerate(ROWS))
    xml = ('<?xml version="1.0" encoding="utf-8"?>\n'
           '<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">\n'
           f'  <file source-language="en" target-language="{lang}" datatype="plaintext" original="DrehzettelBundle">\n'
           f'    <body>\n{body}    </body>\n  </file>\n</xliff>\n')
    (OUT / f"messages.{lang}.xlf").write_text(xml, encoding="utf-8")


keys = [r[0] for r in ROWS]
assert len(keys) == len(set(keys)), "duplicate key"
for lang in ("en", "de"):
    write(lang)
print(len(ROWS), "keys written")
