#!/usr/bin/env python3
"""Builds the review pages next to _build/. Run: python3 _build/build.py [--zip path/to/file.zip]"""
import html
import os
import re
import sys
import zipfile

sys.path.insert(0, os.path.dirname(__file__))
import pages  # noqa: E402
from coverage_page import coverage  # noqa: E402
import game  # noqa: E402

OUT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
E = html.escape

STAMP = "Pages as of 2026-11-06 · branch 22591-admin-make-public-api-breaks-statically-analyzable · base commit e944b563785 plus the uncommitted golden files"

# (file, nav label, must read or skim, minutes)
PAGES = [
    ("index.html", "Overview", "must", 10),
    ("snapshot.html", "Generated files", "must", 20),
    ("coverage.html", "Coverage", "must", 25),
    ("open.html", "Open questions", "must", 15),
    ("plugins.html", "Plugins", "skim", 10),
    ("generator.html", "Generator", "skim", 10),
    ("backtest.html", "Backtest", "skim", 10),
    ("decisions.html", "Decisions", "skim", 5),
    ("glossary.html", "Glossary", "reference", 3),
]

ID_PAGE = {"A": "coverage.html", "B": "coverage.html", "N": "coverage.html", "D": "decisions.html", "S": "decisions.html", "Q": "open.html"}
GITHUB = "https://github.com/shopware/shopware/issues/"


def plain(text):
    return re.sub(r"<[^>]+>", "", re.sub(r'<span class="src-tag[^"]*">.*?</span>', "", text)).strip()


def slug(text):
    return re.sub(r"[^a-z0-9]+", "-", plain(text).lower()).strip("-")


def add_ids(body):
    def repl(m):
        tag, attrs, inner = m.group(1), m.group(2), m.group(3)
        if "id=" in attrs:
            return m.group(0)
        return f'<{tag}{attrs} id="{slug(inner)}">{inner}</{tag}>'
    return re.sub(r"<(h2|h3)([^>]*)>(.*?)</\1>", repl, body)


def toc(body):
    items = []
    for m in re.finditer(r'<(h2|h3)[^>]*id="([^"]+)"[^>]*>(.*?)</\1>', body):
        cls = "l3" if m.group(1) == "h3" else ""
        items.append(f'<a class="{cls}" href="#{m.group(2)}">{plain(m.group(3))}</a>')
    return "\n".join(items)


def linkify(body, current):
    """Links ID mentions (A1, B12, D14, S1, Q3, N8) and #PR numbers in text, never inside pre, code, a or tags."""
    out, skip = [], []
    for part in re.split(r"(<[^>]+>)", body):
        if part.startswith("<"):
            m = re.match(r"<(/?)(pre|code|a|summary)\b", part)
            if m:
                if m.group(1):
                    if skip and skip[-1] == m.group(2):
                        skip.pop()
                elif not part.endswith("/>"):
                    skip.append(m.group(2))
            out.append(part)
            continue
        if skip:
            out.append(part)
            continue

        def id_link(m):
            prefix, num = m.group(1), m.group(2)
            page = ID_PAGE[prefix]
            href = f"#{prefix}{num}" if page == current else f"{page}#{prefix}{num}"
            return f'<a href="{href}">{prefix}{num}</a>'

        part = re.sub(r"(?<![\w#/-])([ABNDSQ])(\d{1,2})(?![\w-])", id_link, part)
        part = re.sub(r"(?<![\w&])#(\d{4,5})\b", lambda m: f'<a href="{GITHUB}{m.group(1)}">#{m.group(1)}</a>', part)
        out.append(part)
    return "".join(out)


def shell(fname, lead, body):
    idx = [f for f, *_ in PAGES].index(fname)
    _, label, mode, minutes = PAGES[idx]
    body = linkify(add_ids(body), fname)
    css = open(os.path.join(OUT, "_build", "review.css")).read()
    nav = "".join(f'<a href="{f}"{" class=\"on\"" if f == fname else ""}>{E(t)}</a>' for f, t, *_ in PAGES)
    mode_text = {"must": "Must read", "skim": "Skim", "reference": "Reference"}[mode]
    prev_link = f'<a href="{PAGES[idx - 1][0]}">← {E(PAGES[idx - 1][1])}</a>' if idx > 0 else "<span></span>"
    next_link = f'<a href="{PAGES[idx + 1][0]}">{E(PAGES[idx + 1][1])} →</a>' if idx < len(PAGES) - 1 else "<span></span>"
    return f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{E(label)} · Admin API Snapshot</title>
<style>
{css}
</style>
</head>
<body>
<header class="top"><div class="in"><a class="brand" href="index.html">Admin API Snapshot <span>review</span></a><nav class="pages">{nav}</nav></div></header>
<div class="layout">
<aside class="toc"><div class="lbl">On this page</div>
{toc(body)}
</aside>
<main>
<div class="eyebrow">Page {idx + 1} of {len(PAGES)} · {mode_text} · about {minutes} min</div>
<h1>{E(label)}</h1>
<p class="lead">{lead}</p>
{body}
<div class="pager">{prev_link}{next_link}</div>
<p class="stamp">{E(STAMP)}</p>
</main>
</div>
</body>
</html>
"""


def outputs():
    """Every generated file with its renderer. Writing and zipping both go through this list."""
    def page(fname, render):
        return fname, lambda: shell(fname, *render())

    return [
        ("play.html", game.render),
        page("index.html", lambda: pages.overview(PAGES)),
        page("snapshot.html", pages.snapshot),
        page("coverage.html", coverage),
        page("open.html", pages.open_questions),
        page("plugins.html", pages.plugins),
        page("generator.html", pages.generator),
        page("backtest.html", pages.backtest),
        page("decisions.html", pages.decisions),
        page("glossary.html", pages.glossary),
    ]


def main():
    files = outputs()
    for fname, render in files:
        with open(os.path.join(OUT, fname), "w") as fh:
            fh.write(render())
        print("wrote", fname)
    if "--zip" in sys.argv:
        write_zip(sys.argv[sys.argv.index("--zip") + 1], [fname for fname, _ in files])


def write_zip(path, fnames):
    readme = "Open play.html for the 6-minute game, or index.html for the full concept.\nEverything works offline.\n"
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("admin-api-snapshot/README.txt", readme)
        for fname in fnames:
            zf.write(os.path.join(OUT, fname), f"admin-api-snapshot/{fname}")
    print("wrote", path)


if __name__ == "__main__":
    main()
