"""Reads the generator test fixtures, so pages quote their numbers instead of copying them."""
import json
import os
import re

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "scripts", "generate-public-api", "__fixtures__")
HISTORY = os.path.join(ROOT, "history")


def fixture_count():
    return sum(1 for name in os.listdir(ROOT) if name != "history" and os.path.isdir(os.path.join(ROOT, name)))


def history_count():
    return sum(1 for name in os.listdir(HISTORY) if os.path.isdir(os.path.join(HISTORY, name)))


def load_case(case):
    with open(os.path.join(HISTORY, case, "case.json")) as fh:
        meta = json.load(fh)
    with open(os.path.join(HISTORY, case, "expected", "findings.txt")) as fh:
        lines = fh.read().splitlines()
    groups, findings, current = {}, [], None
    for line in lines[1:]:
        g = re.match(r"^(\w+) \((\d+)\)$", line)
        f = re.match(r"^  (\S+)  (\S+)  (.+)$", line)
        d = re.match(r"^    (.*)$", line)
        if g:
            current = g.group(1)
            groups[current] = int(g.group(2))
        elif f:
            findings.append({"group": current, "kind": f.group(1), "element": f.group(2), "states": f.group(3), "details": []})
        elif d and findings:
            findings[-1]["details"].append(d.group(1))
    return {"meta": meta, "headline": lines[0], "groups": groups, "findings": findings}


def report_change_lines(case):
    with open(os.path.join(HISTORY, case, "expected", "current.diff")) as fh:
        return sum(1 for line in fh if re.match(r"^[-+](?![-+]{2} )", line))
