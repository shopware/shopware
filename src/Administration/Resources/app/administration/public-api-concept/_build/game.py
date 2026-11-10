"""The game-like entry page play.html: three real PRs to judge, then what the snapshot changes.

Facts come from the backtest fixtures and from pages.py, so the game cannot drift from the concept.
check_game.py verifies the hand-copied diff excerpts against git.
"""
import os
import re

import pages
from fixtures import fixture_count, history_count, load_case, report_change_lines
from game_act3 import AUTHOR, PLUGIN, act3_screens
from game_ui import MINUTES, E, clock, diff_view, finding_view, link_numbers, merge_block_quiz, nav, pr_card, splash, summary_view
from render import md

HERE = os.path.dirname(os.path.abspath(__file__))

LUNCH_SENTENCE = "Everything without @private is public. A generator commits it, and CI fails when a PR breaks it without saying so."

# A line "…" in an excerpt marks skipped lines; check_game.py ignores it.
ROUNDS = [
    {
        "case": "20959",
        "stat": (23, 290, 258),
        "file": "src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig",
        "hint": "Plugins override Twig blocks by name. An override replaces everything between <code>{% block %}</code> and <code>{% endblock %}</code>, unless it calls <code>{% parent %}</code>.",
        "tags": ["public component", "1 of 23 files"],
        "excerpt": """     {% block sw_order_detail_header %}
     <template #smart-bar-header>
-
-        {% block sw_order_detail_header_title %}
-        <h2 v-if="orderIdentifier">
-            {{ $t('sw-order.detail.textHeadline') }} {{ orderIdentifier }}
+        <div
+            v-if="orderIdentifier"
+            class="sw-order-detail__header"
+        >
+            {% block sw_order_detail_header_title %}
+            <h2>
+                {{ $t('sw-order.detail.textHeadline') }} {{ orderIdentifier }}
+            </h2>

             {% block sw_order_detail_header_label_manual_order %}
-            <sw-label
+            <mt-badge
                 v-if="order.createdById"
-                appearance="pill"
-                size="small"
                 class="sw-order-detail__manual-order-label"
             >
                 {{ $t('sw-order.detail.labelManualOrder') }}
-            </sw-label>
-            {% endblock %}
-        </h2>
+            </mt-badge>
+        </div>
+        {% endblock %}
         {% endblock %}""",
        "hot": ["+        <div", "+        </div>"],
        "answer": "block",
        "verdict": "The <code>&lt;div&gt;</code> opens in <code>sw_order_detail_header</code> and closes inside <code>sw_order_detail_header_label_manual_order</code>. A plugin that replaces the inner block also deletes the closing tag.",
        "happened": "Merged after review. Plugins that replace the inner block broke the order page: issue #21108, fixed in #21189.",
        "show": "component:sw-order-detail/crossing:div@sw_order_detail_header>sw_order_detail_header_label_manual_order",
        "extra": "The same PR also moved a block and dropped <code>itemIndex</code> from a slot and from <code>onDeleteItem</code>: {other_breaks} more breaks, spread over {changed} changed lines.",
        "lesson": "Plugins depend on block boundaries, not only block names.",
        "wrong": "The markup still renders, so the build and the tests stay green. The break only shows up once a plugin overrides one of the two blocks.",
        "deeper": ("backtest.html#20959-expected-breaks", "All {breaks} expected breaks of #{pr}"),
    },
    {
        "case": "17339",
        "stat": (4, 169, 57),
        "file": "src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.html.twig",
        "hint": "<code>sw-bulk-edit-custom-fields</code> extends this component and overrides some of its blocks.",
        "tags": ["@private component", "new markup only with flag v6.8.0.0 on", "old markup kept in v-else"],
        "excerpt": """-            {% block sw_custom_field_set_renderer_card_tabs %}
…
+        <template v-if="variant === 'tabs'">
+            <template v-if="feature.isActive('v6.8.0.0')">
+                {% block sw_custom_field_set_renderer_card_mt_tabs %}
+                <mt-tabs
…
+                {% block sw_custom_field_set_renderer_card_mt_tabs_content %}
                 <template
                     v-for="set in visibleCustomFieldSets"
…
                     <div
-                        v-show="active === set.id"
+                        v-show="activeCustomFieldSetTabName === set.id"
                         :class="'sw-custom-field-set-renderer-tab-content__' + set.name"
                     >
-                        {% block sw_custom_field_set_renderer_card_form_renderer %}
+                        {% block sw_custom_field_set_renderer_card_mt_tabs_form_renderer %}
…
+            <template v-else>
+                <sw-tabs
+                    v-if="variant === 'tabs'\"""",
        "hot": ["-                        {% block sw_custom_field_set_renderer_card_form_renderer %}",
                "+                        {% block sw_custom_field_set_renderer_card_mt_tabs_form_renderer %}"],
        "answer": "block",
        "verdict": "The block is renamed in the major branch. <code>sw-bulk-edit-custom-fields</code>, a public component, overrides the old name, so with the flag on its override has nothing to attach to and its own blocks are gone.",
        "happened": "Merged after review. With the major flag on, the bulk edit lost all custom field checkboxes, and nobody noticed for four weeks (issue #19601).",
        "show": "component:sw-bulk-edit-custom-fields/block:sw_custom_field_set_renderer_card_form_renderer_change_field",
        "extra": "All {breaks} breaks exist only in the <b>major</b> state. A snapshot of today's state alone would be clean.",
        "lesson": "A private parent does not protect public children, and a feature flag does not hide a break.",
        "wrong": "Two good reasons to merge, and both are wrong: the component is private, but a public one extends it, and the flag only delays the break to 6.8.",
        "deeper": ("index.html#two-flag-states", "How the two flag states work"),
    },
    {
        "case": "15271-ui-shell",
        "stat": (16, 1729, 1152),
        "file": "src/app/component/structure/sw-admin-menu/sw-admin-menu.html.twig",
        "hint": "This is the UI shell rework. Blocks are deleted in plain sight.",
        "tags": ["@private component", "1 of 16 files"],
        "excerpt": """-        {% block sw_admin_menu_toggle_sidebar %}
-        <button
-            class="sw-admin-menu__toggle"
-            @click="onToggleSidebar"
-        >
-
-            {% block sw_admin_menu_toggle_sidebar_icon %}
-            <mt-icon
-                :name="sidebarCollapseIcon"
-                size="16px"
-            />
-            {% endblock %}
-
-            {% block sw_admin_menu_toggle_sidebar_text %}
-            <span class="collapsible-text">{{ $t('global.sw-admin-menu.linkMinimizeMenu') }}</span>
-            {% endblock %}
-
-        </button>
-        {% endblock %}""",
        "hot": [],
        "answer": "merge",
        "verdict": "<code>sw-admin-menu</code> is <code>@private</code>, so its blocks were never a promise to plugins. The check passes and lists every deletion in the private group, so nobody has to hunt for them.",
        "happened": "Merged. Today a guard test lists the blocks in <code>blocks-list.json</code>. The PR regenerated that list, and the test turned green again without anyone looking at the deletion.",
        "show": "component:sw-admin-menu/block:sw_admin_menu_toggle_sidebar",
        "extra": "",
        "lesson": "Private changes show up in the diff, but they never fail the check.",
        "wrong": "Plugins that used these blocks did lose them. But <code>@private</code> means plugins may not rely on them, so blocking the PR would be noise.",
        "deeper": ("backtest.html#15271-expected-result", "The expected result for #{pr}"),
    },
]


def round_screen(i, r):
    data = load_case(r["case"])
    pr = data["meta"]["pr"]
    _, plus, minus = r["stat"]
    shown = [finding_view(f) for f in data["findings"] if f["element"] == r["show"]]
    breaks = data["groups"].get("Break", 0)
    facts = {"pr": pr, "breaks": breaks, "other_breaks": breaks - 1, "changed": plus + minus}
    tags = "".join(f'<span class="tag">{E(t)}</span>' for t in r["tags"])
    extra = f'<p class="extra">{r["extra"].format(**facts)}</p>' if r["extra"] else ""
    controls, result = merge_block_quiz("Your call, reviewer: does this PR break a plugin?", r["answer"], r["wrong"])
    deeper_href, deeper_label = r["deeper"]
    return f"""
<section class="screen quiz" data-group="act1" data-round="{i}" data-act="Act 1 · Spot the break" hidden>
  <h2 class="kicker" tabindex="-1">Round {i + 1} of {len(ROUNDS)} {clock()}</h2>
  {pr_card(data['meta'], r['stat'])}
  <div class="fname">{E(r['file'])}</div>
  <div class="tags">{tags}</div>
  {diff_view(r['excerpt'], r['hot'])}
  <p class="hint"><b>Hint</b> {r['hint']}</p>
  {controls}
  <div class="reveal" hidden>
    {result}
    <p class="verdict">{r['verdict']}</p>
    <div class="cols">
      <div class="col"><div class="lbl">What really happened</div><p>{link_numbers(r['happened'])}</p></div>
      <div class="col"><div class="lbl">What the snapshot check would print</div>
        {summary_view(data, shown)}
        {extra}
      </div>
    </div>
    <p class="lesson">{r['lesson']}</p>
    {nav((deeper_href, deeper_label.format(**facts)))}
  </div>
</section>"""


def flag_lines():
    out = []
    for line in pages.FLAG_REPORT.split("\n"):
        state = "major" if "[from v6.8.0]" in line else "current" if "[until v6.8.0]" in line else "both"
        name = re.sub(r"\s+\[.*$", "", line)
        depth = (len(name) - len(name.lstrip())) // 2
        out.append(f'<li class="fl s-{state}" style="--d:{depth}">{E(name.strip())}</li>')
    return "".join(out)


def act2_screens():
    report_lines = report_change_lines("20959")
    plus, minus = ROUNDS[0]["stat"][1:]
    start = splash("Act 2 · The fix", "Act 2", "⚙", "The fix", "One rule, a generated snapshot of the public API, and a check in CI. Two short screens.")
    return start + f"""
<section class="screen" data-act="Act 2 · The fix" hidden>
  <h2 class="kicker" tabindex="-1">One rule</h2>
  <p class="rule">Everything without <code>@private</code> is public.</p>
  <p class="sub">The rule is already documented. The snapshot makes it checkable.</p>
  <ol class="pipe">
    <li><b>Source</b><span>components, Twig, modules, en.json</span></li>
    <li><b>Generator</b><span>composer admin:generate-public-api</span></li>
    <li class="hl"><b>public-api/</b><span>committed .api.md and .d.ts files</span></li>
    <li><b>CI</b><span>compares base and head, fails on breaks nobody announced</span></li>
    <li><b>Plugins</b><span>types and a compatibility check</span></li>
  </ol>
  <div class="vs">
    <div class="vsc"><div class="big" data-count="{plus + minus}">{plus + minus}</div><div>changed source lines in #20959</div></div>
    <div class="vsa">→</div>
    <div class="vsc hl"><div class="big" data-count="{report_lines}">{report_lines}</div><div>changed lines in its <code>.api.md</code> report</div></div>
  </div>
  <p class="sub">The report holds names and signatures: components, blocks, methods, props, slots. A change inside a method body leaves it untouched. The bug fix #17081 produces an identical snapshot and no noise. Another break from round 1's PR, a block that moved to a new parent, looks like this to a reviewer:</p>
  <pre class="diff small">{md(pages.DIFF_20959_MOVED)}</pre>
  <div class="nav"><a class="deeper" href="index.html#how-it-works">Go deeper: how it works ↗</a><button class="btn next">Next <kbd>→</kbd></button></div>
</section>

<section class="screen" data-act="Act 2 · The fix" hidden>
  <h2 class="kicker" tabindex="-1">Two flag states</h2>
  <p class="sub">Round 2 hid behind the major flag. So the snapshot is generated twice: <b>current</b> (flag <code>v6.8.0.0</code> off, today's releases) and <b>major</b> (flag on, the next major). Flip it on <code>sw-sales-channel-detail</code>, on trunk today:</p>
  <div class="flag">
    <button class="btn toggle" aria-pressed="false"><kbd>F</kbd> v6.8.0.0 <span class="off">OFF</span><span class="on">ON</span></button>
    <ul class="flist">{flag_lines()}</ul>
  </div>
  <div class="after-flip" hidden>
    <p class="verdict">Three tab blocks vanish in 6.8, and none of them is <code>@deprecated</code>. The example snapshot found this on trunk and lists it as a <code>flag-removal-undeprecated</code> note. A new PR that does the same gets a break in the major state, like round 2.</p>
    <p class="sub">The report shows both states in one tree and marks the difference by version:</p>
    {f'<pre class="diff small">{md(pages.FLAG_REPORT)}</pre>'}
  </div>
  <div class="nav"><a class="deeper" href="index.html#two-flag-states">Go deeper: two flag states ↗</a><button class="btn next">Next <kbd>→</kbd></button></div>
</section>"""


def score(group, maximum):
    return f'<b data-score="{group}" data-max="{maximum}">0</b>/{maximum}'


def end_screen():
    breaks = sum(load_case(r["case"])["groups"].get("Break", 0) for r in ROUNDS)
    return f"""
<section class="screen end" data-act="Game over" hidden>
  <h2 class="kicker" tabindex="-1">Final score</h2>
  <div class="vs final-vs">
    <div class="vsc"><div class="lbl">Act 1 · without the snapshot</div><div class="big">{score("act1", len(ROUNDS))}</div><div><b data-avg="act1">–</b> per PR</div></div>
    <div class="vsa">→</div>
    <div class="vsc hl"><div class="lbl">Act 3 · with the snapshot</div><div class="big">{score("replay", len(ROUNDS))}</div><div><b data-avg="replay">–</b> per PR</div></div>
  </div>
  <p class="rank"></p>
  <p class="sub">PR author: {score("author", len(AUTHOR))} best fixes. Plugin developer: {score("plugin", len(PLUGIN))} fixed on the first try.</p>
  <p class="sub">The original reviews merged all three PRs: 1/{len(ROUNDS)}. The snapshot check would have reported all {breaks} breaks in them before the merge.</p>
  <div class="lunch"><div class="lbl">If you remember one sentence</div><p>{E(LUNCH_SENTENCE)}</p></div>
  <div class="cols">
    <div class="col"><div class="lbl">Where it stands</div>
      <ul class="status">
        <li class="done">Format spec, decisions D1–D37</li>
        <li class="done">Hand-written example snapshot that compiles</li>
        <li class="done">{fixture_count()} test fixtures and {history_count()} real PRs as backtest</li>
        <li class="todo">Generator and CI check: next, after this review</li>
        <li class="todo">Core change for typed overrides (D20)</li>
      </ul>
    </div>
    <div class="col"><div class="lbl">Your move</div>
      <p class="ask-big">Do you back this approach?</p>
      <p>Reply in the thread where you got this link: <b>“I back it”</b>, or the one thing that would stop you.</p>
      <p>Hooked? Read the <a href="index.html">Overview</a> (10 min), then the must-read pages (<a href="index.html#how-to-review">about 70 min</a>). Disagree with a detail? Check the <a href="decisions.html">decisions</a> and the <a href="open.html">open questions</a>.</p>
    </div>
  </div>
  <div class="nav"><button class="btn again">Play again</button><a class="btn primary" href="index.html">Open the full concept →</a></div>
</section>"""


def title_screen():
    return f"""
<section class="screen title" data-act="" hidden>
  <p class="eyebrow">Admin API snapshot · about {MINUTES} minutes</p>
  <h1 tabindex="-1">Break it<br>or not?</h1>
  <p class="intro">Plugins hook into the Administration by name: blocks, methods called via <code>$super</code>, props, slots. Rename or move one, and no build or test fails. The plugin just stops working.</p>
  <p class="intro">Here are {len(ROUNDS)} real PRs. All were reviewed and merged. Your turn to review them, first the way it works today, then with the snapshot.</p>
  <div class="nav"><a class="deeper" href="index.html">Skip the game, read the concept ↗</a><button class="btn primary next">Start <kbd>Enter</kbd></button></div>
</section>"""


def render():
    with open(os.path.join(HERE, "game.css")) as fh:
        css = fh.read()
    with open(os.path.join(HERE, "game.js")) as fh:
        js = fh.read()
    screens = title_screen() + "".join(round_screen(i, r) for i, r in enumerate(ROUNDS)) + act2_screens() + act3_screens(ROUNDS) + end_screen()
    return f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Break It or Not · Admin API Snapshot</title>
<style>
{css}
</style>
</head>
<body>
<header class="hud">
  <span class="act"></span>
  <div class="bar"><span></span></div>
  <span class="score">Score <b>0</b></span>
</header>
<main>
{screens}
</main>
<footer class="foot"><a href="index.html">Full concept</a> · <kbd>←</kbd> <kbd>→</kbd> to move</footer>
<script>
{js}
</script>
</body>
</html>
"""
