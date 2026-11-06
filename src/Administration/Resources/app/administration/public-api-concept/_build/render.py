import html
import re

E = html.escape

KEYWORDS = r"\b(declare|namespace|interface|readonly|export|default|const|function|return|async|await|this|null|import)\b"


def md(text):
    """Report and diff highlighting. Every line is its own block, so diff lines do not leave blank gaps."""
    out = []
    for line in E(text).split("\n"):
        cls = "ln"
        if line.startswith("+") and not line.startswith("+++"):
            cls += " add"
        elif line.startswith("-") and not line.startswith("---"):
            cls += " del"
        elif re.fullmatch(r"[a-z][a-z-]*", line):
            line = f'<span class="sec">{line}</span>'
        if "add" not in cls and "del" not in cls:
            line = re.sub(r"(\[[a-zA-Z][^\]]*\])", r'<span class="m">\1</span>', line)
        out.append(f'<span class="{cls}">{line}</span>')
    return "".join(out)


def ts(text):
    out = []
    for line in E(text).split("\n"):
        s = line.strip()
        if s.startswith("//") or s.startswith("/**") or s.startswith("*"):
            out.append(f'<span class="c">{line}</span>')
            continue
        line = re.sub(r"(&#x27;[^&\n]*?&#x27;)", r'<span class="s">\1</span>', line)
        line = re.sub(KEYWORDS, r'<span class="k">\1</span>', line)
        out.append(line)
    return "\n".join(out)


def code(text, kind="md", wrap=False):
    body = md(text) if kind == "md" else ts(text) if kind == "ts" else E(text)
    cls = "code wrap" if wrap else "code"
    return f'<pre class="{cls}">{body}</pre>'


def single(name, text, kind="md", wrap=False):
    return f'<div style="margin-top:14px"><div class="fname"><b>{E(name)}</b></div>{code(text, kind, wrap)}</div>'


def unit(md_name, md_text, ts_name=None, ts_text=None):
    """An .api.md report, with its .d.ts file collapsed below."""
    dts = ""
    if ts_text:
        dts = f'<details class="dts"><summary>Show the .d.ts file ({E(ts_name)})</summary>{code(ts_text, "ts")}</details>'
    return f'<div style="margin-top:14px"><div class="fname"><b>{E(md_name)}</b></div>{code(md_text, "md")}{dts}</div>'


def src(real):
    return '<span class="src-tag src-real">real golden file</span>' if real else '<span class="src-tag src-illu">illustrative</span>'
