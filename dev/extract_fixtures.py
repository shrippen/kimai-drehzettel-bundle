#!/usr/bin/env python3
"""Rebuilds tests/fixtures/reference_days.json from exported reference timesheets.

Reads reference/<project>/*.pdf and reference/map.json ({"<project>": "<neutral key>"}).
Both stay local (git-ignored): they carry names and film titles.
Anonymized on the way: neutral keys, dates shifted by 52 weeks, no file names.
Usage: python3 dev/extract_fixtures.py   (needs pdftotext)
"""
import glob
import json
import re
import subprocess
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SHIFT = timedelta(days=-364)
MONTHS = {m: i + 1 for i, m in enumerate(
    "Januar Februar März April Mai Juni Juli August September Oktober November Dezember".split())}
DAY_RE = re.compile(r"^(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag), (\d+)\. (\w+) (\d{4})")
TIME_RE = re.compile(r"^\d{1,2}:\d{2}$")


def minutes(text):
    hours, mins = text.replace(" h", "").split(":")
    return int(hours) * 60 + int(mins)


def parse(pdf):
    text = subprocess.run(["pdftotext", "-layout", str(pdf), "-"], capture_output=True, text=True, check=True).stdout
    percents, has_under, rows = [], False, []
    for line in text.splitlines():
        stripped = line.strip()
        if stripped.startswith("Datum"):
            percents = [int(p) for p in re.findall(r"\+ ?(\d+)%", stripped)][:3]
            has_under = "Unterstunden" in stripped
            continue
        m = DAY_RE.match(stripped)
        if not m:
            continue
        tokens = [t for t in stripped[m.end():].split() if t != "h"]
        if len(tokens) < 8 or not TIME_RE.match(tokens[0]):
            continue  # weekday without an entry
        begin, end, pause, work = tokens[:4]
        tiers = tokens[4:7]
        night = tokens[7]
        rest = tokens[8:]
        under = rest.pop(0) if has_under else None
        catering = rest[0]
        cents = re.search(r"([\d.]+,\d{2}) €", stripped)
        day = date(int(m[4]), MONTHS[m[3]], int(m[2])) + SHIFT
        row = {
            "date": day.isoformat(), "begin": begin, "end": end,
            "breakMinutes": minutes(pause), "workMinutes": minutes(work),
            "tierMinutes": [minutes(t) for t in tiers], "nightMinutes": minutes(night),
            "catering": catering == "Ja",
            "cents": int(cents[1].replace(".", "").replace(",", "")) if cents else None,
        }
        if under is not None:
            row["underMinutes"] = minutes(under)
        rows.append(row)
    return {"tierPercents": percents, "rows": rows}


def main():
    names = json.loads((ROOT / "reference" / "map.json").read_text(encoding="utf-8"))
    out = {}
    for project, key in names.items():
        weeks = []
        for pdf in sorted(glob.glob(str(ROOT / "reference" / project / "*.pdf"))):
            week = parse(pdf)
            if week["rows"]:
                weeks.append({"file": f"week {len(weeks) + 1}", **week})
        out[key] = {"weeks": weeks}
    target = ROOT / "tests" / "fixtures" / "reference_days.json"
    target.write_text(json.dumps(out, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")
    for key, data in out.items():
        print(key, sum(len(w["rows"]) for w in data["weeks"]), "rows in", len(data["weeks"]), "weeks")


if __name__ == "__main__":
    main()
