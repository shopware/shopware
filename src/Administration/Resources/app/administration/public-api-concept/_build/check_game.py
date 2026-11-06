#!/usr/bin/env python3
"""Checks play.html against its sources. Run after build.py: python3 _build/check_game.py

- every local link points to an existing page and anchor
- every PR or issue number and every D/Q ID also appears in the concept pages
- every diff excerpt line and diff stat matches the real commits in git
- every finding shown matches findings.txt of its case
"""
import html
import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
import game  # noqa: E402
from fixtures import load_case  # noqa: E402

OUT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = subprocess.run(["git", "rev-parse", "--show-toplevel"], cwd=OUT, capture_output=True, text=True).stdout.strip()
ADMIN_PREFIX = "src/Administration/Resources/app/administration/"


def read(name):
    with open(os.path.join(OUT, name)) as fh:
        return fh.read()


def check_links(play, errors):
    for href in sorted(set(re.findall(r'href="([^"#:]+\.html)(?:#([^"]*))?"', play))):
        page, anchor = href
        path = os.path.join(OUT, page)
        if not os.path.exists(path):
            errors.append(f"link to missing page {page}")
        elif anchor and f'id="{anchor}"' not in read(page):
            errors.append(f"link to missing anchor {page}#{anchor}")


def check_ids(play, errors):
    concept = "".join(read(f) for f in os.listdir(OUT) if f.endswith(".html") and f != "play.html")
    text = html.unescape(re.sub(r"<[^>]+>", " ", play))
    for num in sorted(set(re.findall(r"#(\d{5})\b", text))):
        if f"#{num}" not in concept:
            errors.append(f"#{num} is in the game but not in the concept pages")
    for ident in sorted(set(re.findall(r"\b([DQ]\d{1,2})\b", text))):
        if f'id="{ident}"' not in concept:
            errors.append(f"{ident} is in the game but has no anchor in the concept pages")


def git(*args):
    return subprocess.run(["git", *args], cwd=REPO, capture_output=True, text=True)


def has_commits(meta):
    return all(git("cat-file", "-e", meta[ref]).returncode == 0 for ref in ("base", "head"))


def diff_stat(meta):
    rows = [line.split("\t") for line in git("diff", "--numstat", meta["base"], meta["head"], "--", *meta["files"]).stdout.split("\n") if line]
    return (len(rows), sum(int(a) for a, _, _ in rows), sum(int(d) for _, d, _ in rows))


def excerpt_errors(r, meta):
    diff = set(git("diff", "-U1000", meta["base"], meta["head"], "--", ADMIN_PREFIX + r["file"]).stdout.split("\n"))
    excerpt = r["excerpt"].split("\n")
    missing = [f"{r['case']}: excerpt line not in the real diff: {line!r}" for line in excerpt if line != "…" and line not in diff]
    stray = [f"{r['case']}: highlighted line not in the excerpt: {line!r}" for line in r["hot"] if line not in excerpt]
    return missing + stray


def check_excerpts(errors, warnings):
    for r in game.ROUNDS:
        meta = load_case(r["case"])["meta"]
        if not has_commits(meta):
            warnings.append(f"{r['case']}: commits not in this clone, excerpt not checked")
            continue
        actual = diff_stat(meta)
        if actual != r["stat"]:
            errors.append(f"{r['case']}: stat {r['stat']} differs from git {actual}")
        errors += excerpt_errors(r, meta)


def check_findings(play, errors):
    text = html.unescape(play)
    for r in game.ROUNDS:
        data = load_case(r["case"])
        if not any(f["element"] == r["show"] for f in data["findings"]):
            errors.append(f"{r['case']}: no finding for {r['show']}")
        if data["headline"] not in text:
            errors.append(f"{r['case']}: headline missing in play.html")


def main():
    play = read("play.html")
    errors, warnings = [], []
    check_links(play, errors)
    check_ids(play, errors)
    check_excerpts(errors, warnings)
    check_findings(play, errors)
    for w in warnings:
        print("warning:", w)
    for e in errors:
        print("error:", e)
    print(f"{len(errors)} errors, {len(warnings)} warnings")
    sys.exit(1 if errors else 0)


if __name__ == "__main__":
    main()
