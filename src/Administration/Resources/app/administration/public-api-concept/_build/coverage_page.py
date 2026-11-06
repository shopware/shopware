import html

from coverage_data import AREAS, BREAKS, NOT_COVERED

E = html.escape

GROUP = {
    "none": ("no finding", ""),
    "break": ("break", "t-break"),
    "add": ("addition", "t-add"),
    "allowed": ("allowed", "t-allowed"),
    "accepted": ("accepted", "t-accepted"),
    "private": ("private", ""),
    "info": ("info", "t-info"),
}
COLS = [("spec", "Spec"), ("snap", "Example"), ("fix", "Fixture"), ("hist", "Backtest")]

# Element kind x kind of change. "nr" = no rule yet, "" = not applicable.
MATRIX_COLS = ["Removed or renamed", "Incompatible change", "Moved", "Added", "Made private", "Removed after deprecation"]
MATRIX = [
    ("Component", ["B5", "", "B3", "B26", "B25", "B28"]),
    ("Prop", ["B6", "B10, B11", "", "B26", "B25", "B28"]),
    ("Emit, slot, inject, provide, data", ["B6", "B11", "", "B26", "B25", "B28"]),
    ("Computed", ["B6", "B11", "", "B26", "B25", "B28"]),
    ("Method", ["B6", "B12, B27, B33", "", "B26", "B25", "B28"]),
    ("Block", ["B7", "B2", "B8", "B26", "B25", "B28"]),
    ("HTML element across blocks", ["B34", "", "", "B9", "", ""]),
    ("Route", ["B13", "B15", "B14", "B26", "B25", "B28"]),
    ("Snippet key", ["B16", "B17", "", "B26", "", "B28"]),
    ("Service or store member", ["B18", "B11", "", "B26", "B25", "B28"]),
    ("Service, store, mixin, module", ["B19", "", "", "B26", "B25", "B28"]),
    ("Shopware member, shopware:* export", ["B20", "B11", "", "B26", "B25", "B28"]),
    ("Referenced type", ["B21", "B11, B32", "", "B26", "B25", "B28"]),
    ("Position identifier, data set", ["B22", "nr", "nr", "B26", "", "B28"]),
    ("Native setup binding", ["B23", "B24", "", "B26", "B25", "B28"]),
]
MATRIX_NOTE = "Not every cell is a break: B2 and B3 produce no finding, B27 is an addition, B32, B33 and B34 are info. A dot means the change does not apply."

CROSSING_BEFORE = """{% block sw_order_detail_header %}
    {% block sw_order_detail_header_label_manual_order %}
        <span>Manual order</span>
    {% endblock %}
{% endblock %}"""

CROSSING_AFTER = """{% block sw_order_detail_header %}
    <div class="…">          <- opens here
    {% block sw_order_detail_header_label_manual_order %}
        <span>Manual order</span>
    </div>                  <- closes here
    {% endblock %}
{% endblock %}"""

CROSSING_PLUGIN = """{# plugin override, without {% parent %} #}
{% block sw_order_detail_header_label_manual_order %}
    <span>Manual order (plugin)</span>
{% endblock %}
{# the closing </div> is gone, the page markup breaks #}"""

MOVE_BEFORE = """sw_order_detail_state_card_state
  sw_order_detail_state_card_state_history_button
  sw_order_detail_state_card_state_history_text
    …_history_text_internal_comment
    …_history_text_label
  sw_order_detail_state_card_state_select"""

MOVE_AFTER = """sw_order_detail_state_card_state
  sw_order_detail_state_card_state_history_button
  sw_order_detail_state_card_state_select
    sw_order_detail_state_card_state_history_text
      …_history_text_internal_comment
      …_history_text_label"""


def anchor_id(iid):
    return f'<td class="id"><a href="#{iid}">{iid}</a></td>'


def check_cells(statuses):
    return "".join(f'<td class="ck">{"✓" if key in statuses else ""}</td>' for key, _ in COLS)


def check_heads():
    return "".join(f'<th class="ck">{label}</th>' for _, label in COLS)


def gap_note(gap):
    return f'<div class="gap">{E(gap)}</div>' if gap else ''


def pre(text):
    return f'<pre class="code">{E(text)}</pre>'


def decision_tree():
    rows = [
        ("Is the element, or the unit it belongs to, @private?", '<span class="tag">private</span>', "never blocks"),
        ("Was it removed, and did it carry @deprecated tag:vX that the head version has reached?", '<span class="tag t-allowed">allowed</span>', ""),
        ("Does the PR add a matching entry to accepted-breaks.json?", '<span class="tag t-accepted">accepted</span>', "never blocks"),
        ("Was it removed, renamed, moved or changed incompatibly?", '<span class="tag t-break">break</span>', "fails CI"),
        ("Is it new?", '<span class="tag t-add">addition</span>', ""),
        ("Is it a new deprecation, a wider type, a renamed parameter or a resolved crossing?", '<span class="tag t-info">info</span>', ""),
    ]
    out = ['<div class="tree">']
    for i, (q, tag, note) in enumerate(rows, 1):
        extra = f' <span class="small muted">{note}</span>' if note else ""
        out.append(f'<div class="q"><span class="n">{i}</span><span>{E(q)}</span><span class="yes">yes → {tag}{extra}</span></div>')
        if i < len(rows):
            out.append('<div class="no">no ↓</div>')
    out.append('<div class="no">no → no finding (for example code inside a method)</div></div>')
    return "\n".join(out)


def matrix():
    head = "".join(f"<th>{E(c)}</th>" for c in MATRIX_COLS)
    rows = []
    for kind, cells in MATRIX:
        tds = []
        for c in cells:
            if c == "nr":
                tds.append('<td class="nr">no rule</td>')
            elif not c:
                tds.append('<td class="na">·</td>')
            else:
                tds.append(f"<td>{E(c)}</td>")
        rows.append(f"<tr><td><b>{E(kind)}</b></td>{''.join(tds)}</tr>")
    return f'<div class="tw"><table class="matrix"><thead><tr><th>Element</th>{head}</tr></thead><tbody>{"".join(rows)}</tbody></table></div><p class="small muted">{MATRIX_NOTE}</p>'


def coverage():
    n_areas = sum(len(i) for _, _, i in AREAS)
    n_breaks = sum(len(i) for _, _, i in BREAKS)
    n_gaps = sum(1 for _, _, i in AREAS for e in i if e[5]) + sum(1 for _, _, i in BREAKS for e in i if e[6])
    out = [f"""
<div class="cards">
  <div class="card"><h4>{n_areas} API parts</h4><p>A1–A{n_areas}: what the snapshot records.</p></div>
  <div class="card"><h4>{n_breaks} finding types</h4><p>B1–B{n_breaks}: how the break check classifies a change.</p></div>
  <div class="card"><h4>{len(NOT_COVERED)} not covered</h4><p>N1–N{len(NOT_COVERED)}: left out on purpose or not decided yet.</p></div>
  <div class="card"><h4>{n_gaps} gaps</h4><p>Rows with a red edge and a red Gap note.</p></div>
</div>
<div class="note"><p>Give feedback by ID, for example "A18 should also cover the host component" or "B22 is not a break for us".
Every ID links to its row, and IDs stay stable.</p></div>

<h2>How a change is classified</h2>
<p>The break check asks these questions in order for every element that differs between base and head, in each flag state.
The first yes decides the group.</p>
{decision_tree()}

<h2>Which change leads to which finding</h2>
<p>Each cell links to the finding type below. Red cells have no rule yet.</p>
{matrix()}

<h2>The two template findings, illustrated</h2>
<h3>An element across two blocks (B9)</h3>
<p>Simplified from PR #20959. The page itself renders fine, so the problem is invisible in a normal review.</p>
<div class="pair3">
  <div><div class="lbl">Before</div>{pre(CROSSING_BEFORE)}</div>
  <div><div class="lbl">After</div>{pre(CROSSING_AFTER)}</div>
</div>
<p>A plugin that replaces the inner block without <code>{{% parent %}}</code> drops the closing tag:</p>
{pre(CROSSING_PLUGIN)}
<h3>A block under a new parent (B8)</h3>
<p>The block tree of <code>sw-order-details-state-card</code> before and after PR #20959, as in the expected snapshot diff.
A plugin that overrides <code>…_state_select</code> without <code>{{% parent %}}</code> now also removes the history text.</p>
<div class="pair3">
  <div><div class="lbl">Before</div>{pre(MOVE_BEFORE)}</div>
  <div><div class="lbl">After</div>{pre(MOVE_AFTER)}</div>
</div>

<h2>API parts</h2>
<p>What the snapshot records. Status columns: <b>Spec</b> = rule in <code>FORMAT.md</code>, <b>Example</b> = shown in the example snapshot, <b>Fixture</b> = covered by a test fixture, <b>Backtest</b> = appears in a historical PR.</p>
"""]
    for title, _, items in AREAS:
        out.append(f"<h3>{E(title)}</h3>")
        out.append(f'<div class="tw"><table><thead><tr><th>ID</th><th>Part</th><th>What is recorded</th><th>Example line</th>{check_heads()}</tr></thead><tbody>')
        for iid, name, desc, ex, st, gap in items:
            ex_html = f"<pre>{E(ex)}</pre>" if ex else '<span class="muted small">–</span>'
            cls = ' class="has-gap"' if gap else ""
            out.append(f'<tr id="{iid}"{cls}>{anchor_id(iid)}<td><b>{E(name)}</b></td><td>{E(desc)}{gap_note(gap)}</td><td style="min-width:200px">{ex_html}</td>{check_cells(st)}</tr>')
        out.append("</tbody></table></div>")

    out.append("""
<h2>Finding types</h2>
<p>The finding id is what CI prints and what <code>accepted-breaks.json</code> refers to. Example lines are real lines from the golden files.</p>
""")
    for title, _, items in BREAKS:
        out.append(f"<h3>{E(title)}</h3>")
        out.append(f'<div class="tw"><table><thead><tr><th>ID</th><th>Finding</th><th>Group</th><th>When</th><th>Example line</th>{check_heads()}</tr></thead><tbody>')
        for iid, fid, grp, when, ex, st, gap in items:
            label, gcls = GROUP[grp]
            ex_html = f"<pre>{E(ex)}</pre>" if ex else '<span class="muted small">–</span>'
            cls = ' class="has-gap"' if gap else ""
            out.append(f'<tr id="{iid}"{cls}>{anchor_id(iid)}<td><code>{E(fid)}</code></td><td><span class="tag {gcls}">{label}</span></td><td>{E(when)}{gap_note(gap)}</td><td style="min-width:200px">{ex_html}</td>{check_cells(st)}</tr>')
        out.append("</tbody></table></div>")

    out.append("""
<h2>Not covered</h2>
<p><span class="tag t-ok">decided</span> means left out on purpose. <span class="tag t-warn">open</span> means not mentioned in <code>FORMAT.md</code> yet; Q11 asks whether to add them.</p>
<div class="tw"><table><thead><tr><th>ID</th><th>What plugins can use</th><th>Why it is not covered</th><th>State</th></tr></thead><tbody>""")
    for iid, name, why, state in NOT_COVERED:
        cls, lbl = ("t-ok", "decided") if state == "decided" else ("t-warn", "open")
        out.append(f'<tr id="{iid}">{anchor_id(iid)}<td><b>{E(name)}</b></td><td>{E(why)}</td><td><span class="tag {cls}">{lbl}</span></td></tr>')
    out.append("</tbody></table></div>")
    lead = "Every part of the API that the snapshot records, how the break check classifies each change, and what is not covered. Each row has an ID for feedback."
    return lead, "\n".join(out)
