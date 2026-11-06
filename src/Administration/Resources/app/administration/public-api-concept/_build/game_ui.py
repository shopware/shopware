"""HTML pieces shared by the screens of play.html."""
import html
import re

E = html.escape
GITHUB = "https://github.com/shopware/shopware/issues/"
MINUTES = 8


def gh(num):
    return f'<a href="{GITHUB}{num}" target="_blank" rel="noopener">#{num}</a>'


def link_numbers(text):
    return re.sub(r"(?<![\w&/])#(\d{5})\b", lambda m: gh(m.group(1)), text)


def diff_view(excerpt, hot=(), small=False):
    out = []
    for line in excerpt.split("\n"):
        if line == "…":
            out.append('<span class="ln gap">⋯</span>')
            continue
        cls = "ln add" if line.startswith("+") else "ln del" if line.startswith("-") else "ln"
        if line in hot:
            cls += " hot"
        body = re.sub(r"(\{% (?:end)?block[^%]*%\})", r'<span class="blk">\1</span>', E(line))
        body = re.sub(r"(\[[a-zA-Z][^\]]*\])", r'<span class="m">\1</span>', body)
        out.append(f'<span class="{cls}">{body}</span>')
    return f'<pre class="diff{" small" if small else ""}">{"".join(out)}</pre>'


def chips(groups):
    return "".join(f'<span class="chip g-{g.lower()}">{E(g)} {n}</span>' for g, n in groups.items())


def finding_view(f, details=1):
    lines = "".join(f"<span>{E(d)}</span>" for d in f["details"][:details])
    return f'<div class="finding g-{(f["group"] or "").lower()}"><div class="fh"><b>{E(f["kind"])}</b><span class="st">{E(f["states"])}</span></div><div class="fd">{lines}</div></div>'


def summary_view(data, findings=()):
    status = "fail" if data["groups"].get("Break") else "pass"
    return f'<div class="term"><div class="headline {status}">{E(data["headline"])}</div><div class="chips">{chips(data["groups"])}</div>{"".join(findings)}</div>'


def pr_card(meta, stat):
    files, plus, minus = stat
    return f"""<div class="pr">
    <div class="prh"><span class="prn">{gh(meta['pr'])}</span><span class="prt">{E(meta['title'])}</span><span class="merged">merged</span></div>
    <div class="prs">{files} files · <span class="p">+{plus}</span> <span class="m">−{minus}</span></div>
  </div>"""


def quiz(question, options, banners, notes):
    """Choice buttons and the result line of a quiz screen.

    options: (choice, key, label, grade) with grade best, ok or fail.
    banners: result title per grade. notes: text shown for the picked choice.
    """
    buttons = "".join(
        f'<button class="btn choice g-{grade}" data-choice="{choice}" data-key="{key}" data-grade="{grade}"><kbd>{key.upper()}</kbd> {label}</button>'
        for choice, key, label, grade in options
    )
    titles = "".join(f'<span class="title-{grade}">{E(text)}</span>' for grade, text in banners.items())
    picked = "".join(f'<span class="note" data-for="{choice}" hidden>{text}</span>' for choice, text in notes.items() if text)
    return f"""<div class="ask"><p>{question}</p><div class="choices">{buttons}</div></div>""", f'<div class="result">{titles} {picked}</div>'


def nav(deeper=None, next_label="Next", disabled=False):
    link = f'<a class="deeper" href="{deeper[0]}">Go deeper: {E(deeper[1])} ↗</a>' if deeper else "<span></span>"
    return f'<div class="nav">{link}<button class="btn next"{" disabled" if disabled else ""}>{next_label} <kbd>→</kbd></button></div>'


def merge_block_quiz(question, answer, wrong_note):
    """The reviewer question of Act 1 and its replay in Act 3. answer is merge or block."""
    wrong = "merge" if answer == "block" else "block"
    grade = {answer: "best", wrong: "fail"}
    return quiz(
        question,
        [("merge", "m", "Merge it", grade["merge"]), ("block", "b", "Block it, plugins break", grade["block"])],
        {"best": "Correct", "fail": "Not quite"},
        {wrong: wrong_note},
    )


def clock():
    return '<span class="clock">⏱ <b>0</b> s</span>'


def splash(act, eyebrow, icon, title, mission, steps=(), current=None):
    """Start screen of a section. steps names the parts of the act; current is the index of this one."""
    def step(n, name):
        state = "on" if n == current else "done" if current is not None and n < current else ""
        return f'<li class="{state}">{E(name)}</li>'
    stepper = f'<ol class="stepper">{"".join(step(n, name) for n, name in enumerate(steps))}</ol>' if steps else ""
    return f"""
<section class="screen splash" data-act="{E(act)}" hidden>
  <div class="icon" aria-hidden="true">{icon}</div>
  <p class="eyebrow">{E(eyebrow)}</p>
  <h2 tabindex="-1">{E(title)}</h2>
  <p class="intro">{mission}</p>
  {stepper}
  {nav(next_label="Start")}
</section>"""
