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
    ("drehzettel.day_type.workday", "Workday", "Arbeitstag"),
    ("drehzettel.day_type.travel", "Travel day", "Reisetag"),
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
