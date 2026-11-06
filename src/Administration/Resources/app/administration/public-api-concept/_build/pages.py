import html

from examples import (ACCEPT_JSON, COMP_MD, COMP_TS, ENTRY_TS, INDEX_JSON, MIXIN_MD, MODULE_MD, MODULE_TS,
                      NATIVE_MD, NATIVE_TS, SERVICE_MD, SHOPWARE_MD, SNIPPET_MD, SNIPPET_TS, STORE_MD, TYPES_MD,
                      VM_MD)
import os
import re

from fixtures import load_case
from render import code, single, src, unit

PRIVATE_15271 = load_case("15271-ui-shell")["groups"]["Private"]

GOLDEN = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "public-api")


def golden(path, keep=None, limit=None):
    """The outline of a golden .api.md file, optionally only the lines matching `keep`, shortened to `limit` lines."""
    text = re.search(r"```text\n(.*?)\n```", open(os.path.join(GOLDEN, path)).read(), re.S).group(1)
    lines = text.split("\n")
    if keep:
        lines = [l for l in lines if not l.startswith("  ") or re.search(keep, l)]
    if limit and len(lines) > limit:
        lines = lines[:limit] + ["  …"]
    return "\n".join(lines)


def golden_sections(path, *names):
    """Only the named sections of a golden .api.md outline."""
    out, keep = [], False
    for line in golden(path).split("\n"):
        if re.fullmatch(r"[a-z][a-z-]*", line):
            keep = line in names
            if keep and out:
                out.append("")
        if keep and line:
            out.append(line)
    return "\n".join(out)

E = html.escape

QUESTIONS = {
    "index.html": "Is the idea clear, and is the worked example convincing?",
    "snapshot.html": "Do the .api.md reports read well, and do they contain the right things?",
    "coverage.html": "Is every API part and every kind of change covered and classified the way we want?",
    "open.html": "Do you agree with the proposed answers?",
    "plugins.html": "Is the plugin experience worth the core change and the migration?",
    "generator.html": "Is the workflow right, and is the proposed design reasonable?",
    "backtest.html": "Are the expected results for the real PRs right?",
    "decisions.html": "Do you disagree with any decision, including the new D23 to D30?",
    "glossary.html": "Look up a term.",
}


# --------------------------------------------------------------------------- Overview

RENAME_SOURCE = """// src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js
-        getLoadSalesChannelCriteria() {
+        getSalesChannelCriteria() {"""

RENAME_REPORT = """methods
   generateAccessUrl(): any
-  getLoadSalesChannelCriteria(): any
   getRequiredSalesChannelFieldError(fieldName: any): any
   getRequiredSalesChannelFieldErrorExpression(fieldName: any): any
   getRequiredSalesChannelFields(): any
+  getSalesChannelCriteria(): any
   getSalesChannelEntityName(): any"""

RENAME_SUMMARY = """Public API check failed: 1 unannounced break.

Break (1)
  method_missing  component:sw-sales-channel-detail/method:getLoadSalesChannelCriteria  current, major
    The method is gone, so plugin overrides that call $super('getLoadSalesChannelCriteria') throw at runtime.
    Fix: keep the method and mark it @deprecated tag:v6.8.0, or add an entry to public-api/accepted-breaks.json.
    Possible rename: getSalesChannelCriteria.
    Also affects: sw-sales-channel-create (extends sw-sales-channel-detail).
    At: src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js:303

Addition (1)
  method_added  component:sw-sales-channel-detail/method:getSalesChannelCriteria  current, major
    A new public method that plugins can rely on from now on.
    Fix: mark it @private if it is not meant for plugins."""

RENAME_FIX_A = """/** @deprecated tag:v6.8.0 - Use getSalesChannelCriteria instead */
getLoadSalesChannelCriteria() {
    return this.getSalesChannelCriteria();
},

getSalesChannelCriteria() {
    // the code of the old method
},"""

RENAME_FIX_B = """{
    "accepted": [
        {
            "finding": "method_missing",
            "element": "component:sw-sales-channel-detail/method:getLoadSalesChannelCriteria",
            "justification": "The criteria builder is renamed for 6.8, see UPGRADE-6.8.md.",
            "issue": "https://github.com/shopware/shopware/issues/22591"
        }
    ]
}"""

RENAME_PLUGIN_TS = """Shopware.Component.override('sw-sales-channel-detail', {
    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = this.$super('getLoadSalesChannelCriteria');
            //                           ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
            // error: 'getLoadSalesChannelCriteria' is not a method of sw-sales-channel-detail
            return criteria;
        },
    },
});"""

FLAG_CURRENT = """sw_sales_channel_detail_content_tabs
  sw_sales_channel_detail_content_tab_general
  sw_sales_channel_detail_content_tab_products
  sw_sales_channel_detail_content_tab_theme
  …"""

FLAG_MAJOR = """sw_sales_channel_detail_content_tabs
  sw_sales_channel_detail_content_mt_tabs"""

FLAG_REPORT = """sw_sales_channel_detail_content_tabs
  sw_sales_channel_detail_content_mt_tabs  [from v6.8.0]
  sw_sales_channel_detail_content_tab_general  [until v6.8.0]
  sw_sales_channel_detail_content_tab_products  [after v-if] [until v6.8.0]
  sw_sales_channel_detail_content_tab_theme  [empty] [after v-if] [until v6.8.0]"""


def overview(page_list):
    rows = "".join(
        f'<tr><td><a href="{f}">{E(label)}</a></td><td>{"<b>must read</b>" if mode == "must" else mode}</td><td>{minutes} min</td><td>{E(QUESTIONS[f])}</td></tr>'
        for f, label, mode, minutes in page_list
    )
    body = f"""
<p class="note"><b>Short on time?</b> <a href="play.html">Play the 6-minute version first</a>: three real PRs, you decide whether they break plugins.</p>
<div class="tldr">
  <b>In short</b>
  <ul>
    <li><b>Idea:</b> a generator writes the complete public API of the Administration into committed files. CI compares base and head and fails on breaks that nobody announced.</li>
    <li><b>Decided:</b> what counts as public, the file format, the two flag states, the CI checks, the plugin integration, the block context rules and the plugin-facing names found in the store plugins (D1–D30).</li>
    <li><b>Done:</b> the format spec, a hand-written example snapshot that compiles, 22 test fixtures and 4 real PRs as backtest.</li>
    <li><b>Proposed, not decided:</b> the generator design and the answers to the open questions Q1–Q13.</li>
    <li><b>Wanted from you:</b> feedback on readability of the reports, on coverage gaps, and on the open questions, by ID.</li>
  </ul>
</div>

<h2>How to review</h2>
<div class="meta"><span><b>Total:</b> about 70 minutes for the must-read pages</span></div>
<div class="tw"><table><thead><tr><th>Page</th><th>Priority</th><th>Time</th><th>Question for you</th></tr></thead><tbody>{rows}</tbody></table></div>
<p>Refer to IDs in your feedback: A for API parts, B for finding types, N for what is not covered, D for decisions, S for assumptions, Q for open questions.
You do not need to read <code>FORMAT.md</code>. These pages summarize it; open it only to check a detail.</p>

<h2>The problem</h2>
<p>Coding agents now write most Administration PRs, and we cannot review all of them line by line.
In the Administration the main review risk is breaking extensions.
Plugins hook in through names they only know as strings: components, blocks, methods called via <code>$super</code>, props, services, stores, routes and snippet keys.
If a PR renames or moves one of them, neither the build nor the tests fail, and the plugin stops working.
Today the only way to see what is public is to read the code and know the conventions.</p>
<p>Real cases:</p>
<ul>
  <li><b>#20959</b> added a <code>&lt;div&gt;</code> that opens in one block and closes in another, and moved a block. Plugins that replace the inner block broke the page (issue #21108, fixed in #21189).</li>
  <li><b>#17339</b> renamed blocks only with the major feature flag on. The bulk edit lost all custom field checkboxes, and nobody noticed for four weeks (issue #19601).</li>
  <li><b>#15271</b> deleted blocks of <code>sw-admin-menu</code> during the UI shell rework and regenerated <code>blocks-list.json</code>, so the guard test turned green again without anyone looking at the deletion.
    With the snapshot, every deleted block shows up in the PR diff.
    The check does not fail, because the shell components are <code>@private</code>; it lists the {PRIVATE_15271} changes in the private group.</li>
</ul>

<h2>Today and with the snapshot</h2>
<div class="tw"><table class="cmp"><thead><tr><th>Who</th><th>Today</th><th>With the snapshot</th></tr></thead><tbody>
<tr><td><b>Reviewer</b></td><td>Reads the whole diff and decides from memory what plugins use.</td><td>Reads the check summary. The <code>.api.md</code> diff shows only API changes, never code inside methods.</td></tr>
<tr><td><b>PR author</b></td><td>Gets no signal. Learns about a break from a plugin bug report.</td><td>Gets an annotation on the source line with the reason and a fix, before the review.</td></tr>
<tr><td><b>Plugin developer</b></td><td>Finds out after an update that a block or method is gone.</td><td>Gets a type error for unknown names (after the core change for D20) and a check against the current and the next major.</td></tr>
<tr><td><b>Major release</b></td><td>Removals behind the major flag go unnoticed (#17339).</td><td>Both flag states are checked. A removal without <code>@deprecated</code> is a break.</td></tr>
</tbody></table></div>

<h2>How it works</h2>
<div class="flow">
  <div class="st"><b>Source code</b><span>components, Twig templates, modules, en.json, the Shopware object</span></div>
  <div class="ar">→</div>
  <div class="st"><b>Generator</b><span><code>composer admin:generate-public-api</code></span></div>
  <div class="ar">→</div>
  <div class="st hl"><b>public-api/</b><span>committed: one .api.md report and one .d.ts file per unit</span></div>
  <div class="ar">→</div>
  <div class="st"><b>CI</b><span>drift check and break check, summary and annotations in the PR</span></div>
  <div class="ar">→</div>
  <div class="st"><b>Plugins</b><span>types and a compatibility check</span></div>
</div>
<p>Everything without <code>@private</code> is public API. That rule already exists in <code>technical-docs/11-code-quality/02-unused-code.md</code>; the snapshot makes it checkable.</p>

<h3>Two flag states</h3>
<p>Every file is generated for two flag states.
<b>Current</b> means the major feature flag <code>v6.8.0.0</code> is off, as in today's releases.
<b>Major</b> means it is on, as in the next major release.
The report shows both in one tree and marks what exists in only one of them, by version, so the marker still makes sense when someone reads an old commit: <code>[until v6.8.0]</code> exists in every release before 6.8.0, <code>[from v6.8.0]</code> from 6.8.0 on.</p>
<div class="pair3">
  <div><div class="lbl">Current state</div>{code(FLAG_CURRENT, "plain")}</div>
  <div><div class="lbl">Major state</div>{code(FLAG_MAJOR, "plain")}</div>
</div>
{single("sw-sales-channel-detail.api.md, both states merged", FLAG_REPORT)}
<p>These tab blocks disappear in the major state without a <code>@deprecated</code> tag. The example snapshot found this on trunk.</p>

<h2>One change, end to end</h2>
<p>A PR renames a method that plugins can call. This is what every person involved sees.</p>
<div class="steps">
  <div class="step"><h4>The change {src(False)}</h4><p>The author renames <code>getLoadSalesChannelCriteria</code> to <code>getSalesChannelCriteria</code>. This example comes from <code>FORMAT.md</code>.</p>{code(RENAME_SOURCE)}</div>
  <div class="step"><h4>The diff in the PR</h4><p>The author regenerates and commits the snapshot. The method list in the report changes by two lines. The neighbouring lines are real.</p>{single("module/sw-sales-channel/page/sw-sales-channel-detail.api.md", RENAME_REPORT)}</div>
  <div class="step"><h4>What CI prints {src(False)}</h4><p>The break check fails. The text follows the format in <code>FORMAT.md</code>. The source line also gets an annotation.</p>{code(RENAME_SUMMARY, "plain", wrap=True)}</div>
  <div class="step"><h4>How the author resolves it</h4>
    <p><b>Option A, keep and deprecate.</b> The old name stays and delegates. The check passes with one addition and one info, and the removal is allowed in 6.8.</p>{code(RENAME_FIX_A, "ts")}
    <p><b>Option B, break on purpose.</b> The author adds an entry with a justification. Code owners approve it, and the check passes with the break in the accepted group.</p>{code(RENAME_FIX_B, "plain")}
    <p>If the new method is not meant for plugins, the author marks it <code>@private</code>, and the addition moves to the private group.</p></div>
  <div class="step"><h4>What a plugin developer sees {src(False)}</h4><p>A plugin that still calls the old name gets a type error. This needs the core change for D20; the message text is not specified yet.</p>{code(RENAME_PLUGIN_TS, "ts")}<p>JS plugins get the same finding from <code>admin:check-extensions</code>, with the plugin file and line.</p></div>
</div>

<h2>Current state</h2>
<p>We define the output first, by hand, before any generator code exists.</p>
<div class="tw"><table><thead><tr><th>Part</th><th>State</th><th>Where</th></tr></thead><tbody>
<tr><td>Format spec</td><td><span class="tag t-ok">written, in review</span></td><td><code>public-api/FORMAT.md</code></td></tr>
<tr><td>Decisions and plan</td><td><span class="tag t-ok">written</span></td><td><code>PUBLIC_API_SNAPSHOT_PLAN.md</code></td></tr>
<tr><td>Example snapshot</td><td><span class="tag t-ok">hand-written, compiles with tsc</span></td><td><code>public-api/</code>: sw-sales-channel, sw-order, two mixins, the Shopware object, a service, a store, the shopware:* modules, referenced types</td></tr>
<tr><td>Test fixtures</td><td><span class="tag t-ok">22 written</span></td><td><code>scripts/generate-public-api/__fixtures__/</code></td></tr>
<tr><td>Historical cases</td><td><span class="tag t-ok">4 written</span></td><td>#20959, #17339, #15271, #17081</td></tr>
<tr><td>Generator, CI checks</td><td><span class="tag">not started</span></td><td>after this review</td></tr>
<tr><td>Core change for typed overrides (D20, D21)</td><td><span class="tag">not started</span></td><td><code>global.types.ts</code>, <code>async-component.factory.ts</code></td></tr>
<tr><td>Replacing the old lists, plugin tooling</td><td><span class="tag">not started</span></td><td></td></tr>
</tbody></table></div>
<p>What the snapshot does not cover, on purpose or not decided yet, is listed in N1–N13.</p>
"""
    lead = "A generated, committed list of everything plugins can use in the Administration, so a PR that breaks it shows up in the diff and fails CI."
    return lead, body


# --------------------------------------------------------------------------- Generated files

DIFF_20959_MOVED = """--- a/module/sw-order/component/sw-order-details-state-card.api.md
+++ b/module/sw-order/component/sw-order-details-state-card.api.md
   stateName: any
-  stateSelectBackgroundStyle: any
+  stateSelectBackgroundStyle: any  [deprecated tag:v6.8.0]
   stateTransitionMethod: any
   …
   sw_order_detail_state_card
     sw_order_detail_state_card_state
       sw_order_detail_state_card_state_history_button
-      sw_order_detail_state_card_state_history_text
-        sw_order_detail_state_card_state_history_text_internal_comment
-        sw_order_detail_state_card_state_history_text_label
       sw_order_detail_state_card_state_select
+        sw_order_detail_state_card_state_history_text
+          sw_order_detail_state_card_state_history_text_internal_comment
+          sw_order_detail_state_card_state_history_text_label"""

DIFF_20959_SCOPE = """--- a/module/sw-order/component/sw-order-line-items-grid.api.md
+++ b/module/sw-order/component/sw-order-line-items-grid.api.md
   …
         sw_order_line_items_grid_grid_actions
-          sw_order_line_items_grid_grid_actions_delete  [scope: item, itemIndex]
-            sw_order_line_items_grid_grid_actions_delete_label  [scope: item, itemIndex]
+          sw_order_line_items_grid_grid_actions_delete  [scope: item]
+            sw_order_line_items_grid_grid_actions_delete_label  [scope: item]
   …"""

DIFF_20959_CROSSING = """--- a/module/sw-order/page/sw-order-detail.api.md
+++ b/module/sw-order/page/sw-order-detail.api.md
   …
       sw_order_detail_header_title_new  [after v-if]
     sw_order_detail_language_switch

 crossings
-  v-if  sw_order_detail_header_title -> sw_order_detail_header_title_new
+  <div>  sw_order_detail_header -> sw_order_detail_header_label_manual_order"""

DIFF_20959 = "\n\n".join([DIFF_20959_MOVED, DIFF_20959_SCOPE, DIFF_20959_CROSSING])

SUMMARY_20959 = """Public API check failed: 8 unannounced breaks.

Break (8)
  block_moved_parent  component:sw-order-details-state-card/block:sw_order_detail_state_card_state_history_text  current, major
    The block moved from sw_order_detail_state_card_state to sw_order_detail_state_card_state_select, so plugin overrides of sw_order_detail_state_card_state_select without {% parent %} now drop it.
    Fix: keep the block under sw_order_detail_state_card_state, or add an entry to public-api/accepted-breaks.json.
    At: src/module/sw-order/component/sw-order-details-state-card/sw-order-details-state-card.html.twig:28
  block_scope_missing  component:sw-order-line-items-grid/block:sw_order_line_items_grid_grid_actions_delete/scope:itemIndex  current, major
    The template variable itemIndex from the actions slot is no longer available in block sw_order_line_items_grid_grid_actions_delete, so plugin overrides that use itemIndex fail at render time.
    Fix: keep itemIndex in the slot props, or add an entry to public-api/accepted-breaks.json.
    At: src/module/sw-order/component/sw-order-line-items-grid/sw-order-line-items-grid.html.twig:316
  … 4 more block_scope_missing for the other action blocks
  element_crosses_block  component:sw-order-detail/crossing:div@sw_order_detail_header>sw_order_detail_header_label_manual_order  current, major
    The <div> element opens in block sw_order_detail_header and closes in block sw_order_detail_header_label_manual_order, so plugin overrides of either block without {% parent %} break the markup.
    Fix: open and close the element inside the same block, or add an entry to public-api/accepted-breaks.json.
    At: src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig:8
  method_signature_changed  component:sw-order-line-items-grid/method:onDeleteItem  current, major
    The method lost its parameter itemIndex, so plugin overrides that read the second argument get undefined, because the template now calls onDeleteItem(item).
    Fix: keep the parameter and keep passing it, or add an entry to public-api/accepted-breaks.json.
    At: src/module/sw-order/component/sw-order-line-items-grid/index.js:330

Addition (12)
  …
Info (6)
  crossing_resolved  component:sw-order-detail/crossing:v-if@sw_order_detail_header_title>sw_order_detail_header_title_new  current, major
    The v-if chain of block sw_order_detail_header_title no longer continues in block sw_order_detail_header_title_new.
  …"""

ANNOT_20959 = """::error file=src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-line-items-grid/index.js,line=330,title=method_signature_changed::The method onDeleteItem lost its parameter itemIndex, so plugin overrides that read the second argument get undefined. Fix: keep the parameter and keep passing it, or accept the break."""


def snapshot():
    body = f"""
<div class="note"><p>Excerpts marked {src(True)} come from the golden files on the branch, shortened where you see <code>…</code>.
Excerpts marked {src(False)} show the format with an invented case.
The <code>.d.ts</code> files are collapsed; open them when you want to check the types.</p></div>

<h2>What a PR shows</h2>
<h3>The snapshot diff {src(True)}</h3>
<p>The expected diff of PR #20959: a new deprecation, a moved block subtree, action blocks that lost the slot variable <code>itemIndex</code>, and a <code>v-if</code> chain crossing replaced by an element crossing.
GitHub collapses these files by default; reviewers open them when the check summary points there.</p>
{code(DIFF_20959)}
<h3>The check summary {src(True)}</h3>
<p>The same PR, as the break check prints it to the job log and the step summary.</p>
{code(SUMMARY_20959, "plain", wrap=True)}
<h3>Annotations {src(False)}</h3>
<p>One GitHub annotation per finding, on the head source line. Breaks are errors, everything else is a notice. This line follows the annotation rules for the third #20959 finding.</p>
{code(ANNOT_20959, "plain", wrap=True)}
<h3>HTML page</h3>
<p>The break check also writes a simple page as a workflow artifact: the verdict, one card per finding, and base and head side by side with the changed lines marked.
An expected page exists for each historical case, for example <code>__fixtures__/history/20959/expected/report.html</code>.</p>

<h2>How to read a report</h2>
<div class="tw"><table><thead><tr><th>Marker</th><th>Meaning</th></tr></thead><tbody>
<tr><td><code>[until v6.8.0]</code> <code>[from v6.8.0]</code></td><td>Exists in one flag state only: in every release before 6.8.0, or from 6.8.0 on</td></tr>
<tr><td><code>[after v-if]</code></td><td>The block follows an open <code>v-if</code> chain, so an override may start with <code>v-else-if</code> or <code>v-else</code></td></tr>
<tr><td><code>[scope: index, item]</code></td><td>Template variables from an enclosing <code>v-for</code> or slot that an override can use</td></tr>
<tr><td><code>[deprecated tag:vX]</code> <code>[experimental]</code></td><td>Tags from the source; the full message is in the <code>.d.ts</code> file</td></tr>
<tr><td><code>[private]</code></td><td>Not public API; on every line of a private unit</td></tr>
<tr><td><code>[inherited]</code> <code>[replaced]</code> <code>[keepsParent]</code></td><td>Where a block of an extend child comes from: unchanged from the parent, redeclared without <code>{{% parent %}}</code>, or redeclared with it</td></tr>
<tr><td><code>[empty]</code> <code>[repeated]</code></td><td>Block without content, block declared more than once</td></tr>
</tbody></table></div>
<p>Under <code>notes</code> a report lists facts that never fail the check: <code>flag-removal-undeprecated</code> (removed in major without a tag), <code>visibility-conflict</code>, <code>duplicate-snippet-key</code> and <code>dynamic-block</code>.
A report never contains counts or "used by" lists, because those change for reasons outside the unit.</p>

<h2>Folder layout</h2>
<p>The snapshot lives in <code>src/Administration/Resources/app/administration/public-api/</code> and mirrors <code>src/</code>.
Each unit (a component, module, mixin, snippet file, service or store) has an <code>.api.md</code> report and a <code>.d.ts</code> file at the path of its source.</p>
<div class="tw"><table><thead><tr><th>Path</th><th>Content</th></tr></thead><tbody>
<tr><td><code>module/&lt;dir&gt;/&lt;path&gt;/&lt;name&gt;.*</code></td><td>One unit per component of a module</td></tr>
<tr><td><code>module/&lt;dir&gt;/module.*</code></td><td>The module and all its named routes</td></tr>
<tr><td><code>module/&lt;dir&gt;/snippet.*</code></td><td>Snippet keys of the module's <code>en.json</code></td></tr>
<tr><td><code>app/component/&lt;path&gt;/&lt;name&gt;.*</code></td><td>Global components</td></tr>
<tr><td><code>app/mixin/&lt;name&gt;.*</code>, <code>app/store/&lt;file&gt;.*</code></td><td>Mixins and Pinia stores</td></tr>
<tr><td><code>core/shopware.*</code>, <code>core/service/&lt;file&gt;.*</code>, <code>core/virtual-modules.*</code></td><td>The Shopware object, services, shopware:* modules</td></tr>
<tr><td><code>types/&lt;path&gt;.*</code></td><td>Source types that other units reference (D16)</td></tr>
<tr><td><code>&lt;name&gt;.major.d.ts</code></td><td>Only when the major state of a unit differs</td></tr>
<tr><td><code>accepted-breaks.json</code></td><td>Intended breaks, written by hand (D14)</td></tr>
<tr><td><code>FORMAT.md</code>, <code>_base/</code>, <code>current.d.ts</code>, <code>major.d.ts</code>, <code>index.json</code></td><td>Spec, base types, entry points and machine index, see "Internal files"</td></tr>
</tbody></table></div>

<h2>One example per unit</h2>
<h3>Component {src(True)}</h3>
<p>The report reads like a declaration outline. Blocks form a tree, indented by nesting.
In the <code>.d.ts</code> file every block names its parent, so a moved block is one changed line.</p>
{unit("module/sw-sales-channel/page/sw-sales-channel-detail.api.md", COMP_MD, "sw-sales-channel-detail.d.ts", COMP_TS)}

<h3>Extend child {src(True)}</h3>
<p>The generator resolves the Twig extend and lists the blocks the child really has, with their origin.</p>
{unit("module/sw-sales-channel/page/sw-sales-channel-create.api.md", '''component sw-sales-channel-create  public  js
  extends sw-sales-channel-detail
  registered src/module/sw-sales-channel/index.js
  source src/module/sw-sales-channel/page/sw-sales-channel-create/index.js

blocks
  sw_sales_channel_detail  [inherited]
    sw_sales_channel_detail_actions  [inherited]
      sw_sales_channel_detail_actions_save  [inherited]
    …''')}

<h3>Native setup SFC {src(True)}</h3>
<p>Core ships no native SFC yet, so this is the fixture <code>native-sfc</code>. Bindings listed in <code>swDefinePublic</code> are public, all others private.</p>
{unit("app/component/base/sw-native-example.api.md", NATIVE_MD, "sw-native-example.d.ts (shortened)", NATIVE_TS)}

<h3>Module and routes {src(True)}</h3>
{unit("module/sw-sales-channel/module.api.md", MODULE_MD, "module.d.ts", MODULE_TS)}

<h3>Snippets {src(True)}</h3>
<p>Keys with their placeholders and number of plural forms. Texts are never stored, so rewording a text changes nothing.</p>
{unit("module/sw-sales-channel/snippet.api.md", SNIPPET_MD, "snippet.d.ts", SNIPPET_TS)}

<h3>Shopware object {src(True)}</h3>
{unit("core/shopware.api.md", SHOPWARE_MD)}

<h3>Service and store {src(True)}</h3>
{unit("core/service/api/config.api.service.api.md", SERVICE_MD)}
{unit("app/store/notification.store.api.md", STORE_MD)}

<h3>Mixin {src(True)}</h3>
<p><code>notification</code> is <code>@private</code> on its export, so every line is marked private.</p>
{unit("app/mixin/notification.api.md", MIXIN_MD)}

<h3>shopware:* modules {src(True)}</h3>
{unit("core/virtual-modules.api.md", VM_MD)}

<h3>Referenced source types {src(True)}</h3>
<p>A type from <code>src/</code> that another unit uses gets a copy, so a change to it shows up (D16).</p>
{unit("types/app/store/notification.store.api.md", TYPES_MD)}

<h3>Template refs and CSS classes {src(True)}</h3>
<p>Every component lists the refs of its template and its static classes. Classes that exist in one flag state only carry the version marker.</p>
{single("module/sw-order/page/sw-order-detail.api.md (refs and classes)", golden_sections("module/sw-order/page/sw-order-detail.api.md", "refs", "classes"))}

<h3>Filters and directives {src(True)}</h3>
<p>The names that core registers, with the filter signatures copied from source.</p>
{unit("app/filter.api.md", golden("app/filter.api.md", keep=r"^  (asset|currency|date|truncate)\("))}
{unit("app/directive.api.md", golden("app/directive.api.md"))}

<h3>ACL privileges {src(True)}</h3>
{unit("module/sw-product/acl.api.md", golden("module/sw-product/acl.api.md"))}

<h3>Registries {src(True)}</h3>
<p>Entries core registers into service registries. Plugins add their own entries; those are not part of the snapshot.</p>
{unit("core/registry/cms.api.md (shortened)", golden("core/registry/cms.api.md", keep=r"commerce|^  text$|product-heading|product-listing|^  image$"))}
{unit("core/registry/rule-conditions.api.md (shortened)", golden("core/registry/rule-conditions.api.md", keep=r"^  (cart|customer|general)$|cartCartAmount|customerOrderCount"))}
{unit("core/registry/settings-groups.api.md", golden("core/registry/settings-groups.api.md"))}

<h3>Event bus and feature flags {src(True)}</h3>
{unit("core/event-bus.api.md (shortened)", golden("core/event-bus.api.md", keep=r"sw-product-detail|sw-language-switch"))}
{unit("core/feature-flags.api.md (shortened)", golden("core/feature-flags.api.md", keep=r"v6\.8\.0\.0|METEOR"))}

<h3>SCSS variables and mixins {src(True)}</h3>
<p>What plugins import with <code>@import "~scss/variables"</code> and <code>~scss/mixins</code>.</p>
{unit("scss/variables.api.md (shortened)", golden("scss/variables.api.md", keep=r"color-shopware-brand-500|color-gray-300|border-radius-default|font-size-xs"))}
{unit("scss/mixins.api.md (shortened)", golden("scss/mixins.api.md", limit=6))}

<h3>Intended breaks {src(True)}</h3>
<p>A break made on purpose gets an entry with a mandatory justification. Code owners approve changes to this file.</p>
{single("accepted-breaks.json", ACCEPT_JSON, "plain")}

<h2>Internal files</h2>
<p>Needed by tools, not by reviewers.</p>
<details class="box"><summary>Entry points: current.d.ts and major.d.ts</summary>
<p>Each references all files of its flag state and ends with checks that <code>tsc</code> evaluates. <code>Conforms</code> fails when an entry does not have the expected shape. <code>CheckReferences</code> fails when an <code>extends</code>, mixin, route component, parent or redirect names something that does not exist.</p>
{single("current.d.ts", ENTRY_TS, "ts")}</details>
<details class="box"><summary>Machine index: index.json</summary>
<p>Replaces <code>blocks-list.json</code>, <code>position-identifiers.json</code>, <code>data-sets.json</code> and <code>shopware-modules.json</code> for Vite, Jest and ESLint (D15).</p>
{single("index.json", INDEX_JSON, "plain")}</details>
<details class="box"><summary>Determinism rules</summary>
<ul>
  <li>Keys and lines are sorted by codepoint, sections come in a fixed order.</li>
  <li>Committed files contain no timestamps, versions, absolute paths or line numbers.</li>
  <li>Two runs on the same commit produce byte-identical output.</li>
  <li>The generator copies types from the source text and never prints them from the type checker. JS members become <code>AdminApi.Untyped</code>.</li>
</ul></details>
"""
    lead = "What the generator writes and what a PR shows to reviewers. Start with the PR view, then check whether the reports contain the right things."
    return lead, body


# --------------------------------------------------------------------------- Generator

def generator():
    body = """
<div class="note warn"><p>The generator does not exist yet. The workflow follows D7, D10 and D15. The design further down is a proposal.</p></div>

<h2>Workflow</h2>
<h3>Generating locally</h3>
<pre class="code"><span class="c"># inside the Docker container, like the existing list generators</span>
composer admin:generate-public-api</pre>
<ul>
  <li>Writes everything below <code>public-api/</code> except the hand-written files: <code>FORMAT.md</code>, <code>.gitattributes</code>, <code>_base/</code> and <code>accepted-breaks.json</code>.</li>
  <li>Covers the whole core Administration and both flag states in one run.</li>
  <li>The author commits the result together with the code change.</li>
  <li>Replaces the four existing list generators. Their consumers (the Vite plugin for shopware:* modules, the Jest setup, an ESLint rule and the successor of <code>meta.spec.js</code>) read <code>index.json</code>.</li>
</ul>

<h3>CI: drift check</h3>
<p>Makes sure the committed snapshot matches the code.</p>
<ol>
  <li>Regenerate the snapshot. Fail when <code>git diff</code> is not empty.</li>
  <li>Type-check <code>current.d.ts</code> and <code>major.d.ts</code>. This runs the shape and reference checks in the entry points.</li>
  <li>Fail only on errors in files below <code>public-api/</code>. The same program also reports errors in <code>node_modules</code> and in local declaration files, which the snapshot does not control.</li>
</ol>

<h3>CI: break check</h3>
<p>Decides whether the PR breaks the API.</p>
<ol>
  <li>Run the generator of the target branch on the base commit and on the head commit. Using the target branch's generator, not the PR's, means a PR cannot change the generator to hide its own break. The check never reads the committed snapshot, so regenerating it cannot hide a break either.</li>
  <li>Compare base and head per flag state and classify every difference, see the decision tree on the <a href="coverage.html#how-a-change-is-classified">Coverage</a> page.</li>
  <li>Apply the entries that the PR added to <code>accepted-breaks.json</code>.</li>
  <li>Print the summary, add the annotations and write the HTML page. The job fails when a break is not accepted.</li>
</ol>

<h2>Proposed design</h2>
<div class="proposal"><span class="tag t-warn">Proposal</span>
<p>Not decided. The steps follow the rules in <code>FORMAT.md</code>; the tools are suggestions.</p></div>
<div class="flowgrid">
  <div class="st"><b>1 Discover</b><span>find all registrations, starting from the entry files</span></div>
  <div class="st"><b>2 Extract</b><span>read each source kind with its own parser</span></div>
  <div class="st"><b>3 Resolve</b><span>extends chains, mixins, core overrides, referenced types</span></div>
  <div class="st"><b>4 Flag states</b><span>evaluate feature checks for current and major</span></div>
  <div class="st hl"><b>5 Model</b><span>one in-memory model per flag state</span></div>
  <div class="st"><b>6 Print</b><span>.api.md, .d.ts and index.json from the model</span></div>
</div>

<h3>Sources and how to read them</h3>
<div class="tw"><table><thead><tr><th>Source</th><th>What we read</th><th>Proposed tool</th></tr></thead><tbody>
<tr><td>Registrations</td><td><code>Component.register/extend/override</code>, <code>Mixin.register</code>, <code>Module.register</code>, service providers, API services, Pinia stores. Reuse the file rules of <code>scripts/public-api-source-files.ts</code>.</td><td><code>ts-morph</code>, already installed</td></tr>
<tr><td>Component configs in JS and TS</td><td>Props, emits, inject, provide, the <code>data()</code> return value, computed, methods with parameters, JSDoc tags.</td><td><code>ts-morph</code> on the syntax tree, without the type checker</td></tr>
<tr><td>Twig templates</td><td>Block tree, extend resolution, deprecation comments, <code>&lt;slot&gt;</code>, position identifiers, data sets, and elements that cross blocks.</td><td>Twig tokenizer plus HTML parser; check what the existing block list generator offers (Q9)</td></tr>
<tr><td>Native SFCs</td><td><code>defineProps</code>, <code>defineEmits</code>, <code>defineModel</code>, setup bindings and their kinds, <code>swDefinePublic</code>, <code>&lt;sw-block&gt;</code>.</td><td><code>@vue/compiler-sfc</code>; <code>vue-component-meta</code> only if needed (Q10)</td></tr>
<tr><td>Feature flags</td><td>The generator evaluates each <code>feature.isActive</code> check twice, with the major flags off and on. A condition it cannot evaluate counts as true in both states. The major flags come from <code>feature.yaml</code>.</td><td>Own evaluator on the template and script syntax tree</td></tr>
<tr><td>Snippets</td><td>Every <code>en.json</code> the server loads, with placeholders and plural forms.</td><td>JSON plus a small message parser</td></tr>
<tr><td>Shopware object, services, stores, types</td><td>Members of <code>src/core/shopware.ts</code>, <code>ServiceContainer</code>, <code>PiniaRootState</code> and the referenced declarations.</td><td><code>ts-morph</code>, copying the declaration text</td></tr>
</tbody></table></div>

<h3>Model and printers</h3>
<ul>
  <li>The break check compares models, not files, so the output format can change without changing the classification.</li>
  <li>Three printers write the reports, the <code>.d.ts</code> files and <code>index.json</code> from the same model.</li>
  <li>A unit gets a <code>.major.d.ts</code> only when its major state differs.</li>
</ul>

<h3>Break check</h3>
<ul>
  <li>Check out base and head in separate worktrees and run the generator on both.</li>
  <li>Match elements by their element id, for example <code>component:sw-order-detail/method:onSave</code>, never by file path.</li>
  <li>Classify, detect renames, then apply <code>accepted-breaks.json</code>. Only entries the PR adds count.</li>
</ul>

<h3>Testing</h3>
<p>The generator is built against the 22 fixtures and the 4 historical cases. The done criteria are on the <a href="backtest.html#done-criteria">Backtest</a> page.</p>

<h3>Open design questions</h3>
<p>Q8 (CI runtime), Q9 (Twig parsing) and Q10 (native SFC extraction).</p>
"""
    lead = "The agreed workflow for generating and checking the snapshot, and a proposed design for the generator, which does not exist yet."
    return lead, body


# --------------------------------------------------------------------------- Plugins

CHECK_EXTENSIONS_STEPS = [
    "Find every core name the plugin uses as a string: override and extend targets, overridden members, <code>$super('…')</code> calls, block names in templates, route names, <code>$t</code> keys, position identifiers and data set ids.",
    'Write each use as a typed constant, with the plugin file and line as a comment.',
    'Type-check those constants against <code>current.d.ts</code> and against <code>major.d.ts</code>, and map each error back to the plugin line.',
    'Warn about uses of <code>@private</code> or <code>@deprecated</code> items.',
    'Warn when an override defines a method the target does not have, which happens after a rename in core.',
    'Report a lazy override whose wrapped target differs from the overridden component.',
    'Check the context of every overridden block: variables the override uses must be scope variables of the block, and an override that starts with <code>v-else-if</code> or <code>v-else</code> needs a block that follows an open <code>v-if</code> chain.',
    'Check filter and directive names, <code>acl.can</code> keys and privilege parents, registry lookups and groups, event bus names, feature flag names, <code>$refs</code> reads, and in plugin SCSS the <code>~scss/</code> variables, mixins and <code>.sw-*</code> selectors. A missing class is a warning.',
    'Warn about <code>$parent</code> access and about imports from <code>src/</code>, and accept both <code>{% parent %}</code> and <code>{{ parent() }}</code>.',
]


def steps_list(steps):
    return "<ol>\n" + "".join(f"  <li>{step}</li>\n" for step in steps) + "</ol>"


def plugins():
    body = f"""
<p>Plugin developers get two things: typed overrides in their editor, and a check that lists every core name their plugin uses that no longer exists in the current or the next major.</p>

<h2>Loading the snapshot</h2>
<p>The snapshot ships in the Administration package. <code>extension-tooling/admin-types.d.ts</code> references it, so plugins that already use that file get the types without a change.</p>
<pre class="code"><span class="c">/// &lt;reference types="node" /&gt;</span>
<span class="c">/// &lt;reference path="../public-api/current.d.ts" /&gt;</span>

<span class="k">import</span> <span class="s">'../src/global.types'</span>;</pre>
<p>A second file, <code>admin-types.major.d.ts</code>, references <code>major.d.ts</code> to check against the next major.</p>

<h2>Typed Component.override (D20)</h2>
<p>Plugins keep calling <code>Shopware.Component.override</code> as today, without a cast.</p>
<pre class="code">Shopware.Component.override(<span class="s">'sw-sales-channel-detail'</span>, {{
    inject: [<span class="s">'themeService'</span>],
    methods: {{
        getLoadSalesChannelCriteria() {{
            <span class="k">const</span> criteria = <span class="k">this</span>.$super(<span class="s">'getLoadSalesChannelCriteria'</span>);
            criteria.addAssociation(<span class="s">'themes'</span>);
            <span class="k">return</span> criteria;
        }},
        <span class="k">async</span> onSave() {{
            <span class="k">this</span>.isLoading = true;
            <span class="k">await</span> <span class="k">this</span>.$super(<span class="s">'onSave'</span>);
        }},
    }},
}});</pre>
<ul>
  <li><code>this</code> knows the props, data, computed values, methods and injects of the component, its parents, its mixins and the members core overrides add.</li>
  <li><code>$super</code> accepts only method and computed names of the target and checks the number of arguments.</li>
  <li>An override of a native component sees only its public bindings, props and injects, as at runtime.</li>
  <li>Names the snapshot does not know, for example components of another plugin, keep today's untyped behaviour.</li>
</ul>
<div class="note bad"><p><b>This needs a core change first.</b>
Core has to type <code>Component.override</code> through a global interface, <code>ComponentOverrideFn</code>, so that the snapshot can add its typed signature.
The change is small and does not touch the runtime, but it is not done yet.
We tested the example snapshot without it, and <code>this.$super('doesNotExist')</code> compiles.</p></div>

<h2>Override files loaded lazily (D21)</h2>
<p>Many plugins keep each override in its own file and load it with <code>() =&gt; import()</code>. SwagCommercial does this for 152 of its 246 overrides.
A new helper gives those files the same types:</p>
<pre class="code"><span class="c">// sw-sales-channel-detail.override.ts</span>
<span class="k">export default</span> Shopware.Component.wrapOverrideConfig(<span class="s">'sw-sales-channel-detail'</span>, {{
    methods: {{
        getLoadSalesChannelCriteria() {{
            <span class="k">const</span> criteria = <span class="k">this</span>.$super(<span class="s">'getLoadSalesChannelCriteria'</span>);
            criteria.addAssociation(<span class="s">'themes'</span>);
            <span class="k">return</span> criteria;
        }},
    }},
}});

<span class="c">// index.ts</span>
Shopware.Component.override(<span class="s">'sw-sales-channel-detail'</span>, () =&gt; <span class="k">import</span>(<span class="s">'./sw-sales-channel-detail.override'</span>));</pre>
<p>The helper returns the config unchanged. If the file was wrapped for another component than the one it overrides, TypeScript reports an error.</p>

<h2>Name helpers</h2>
<ul>
  <li><code>AdminApi.BlockName&lt;'sw-sales-channel-detail'&gt;</code>, <code>AdminApi.SuperName&lt;N&gt;</code>, <code>AdminApi.Instance&lt;N&gt;</code></li>
  <li><code>AdminApi.RouteName</code>, <code>AdminApi.MiddlewareRouteName</code>, <code>AdminApi.RouteParams&lt;R&gt;</code></li>
  <li><code>AdminApi.SnippetKey</code>, <code>AdminApi.PositionIdentifierName</code>, <code>AdminApi.DataSetId</code></li>
  <li>Setting <code>strictPublicApi: true</code> in <code>AdminApi.Options</code> hides private components and mixins from these helpers.</li>
</ul>

<h2>admin:check-extensions</h2>
<p>Most plugins are JS and Twig, which TypeScript does not check. The extended command covers them in {len(CHECK_EXTENSIONS_STEPS)} steps:</p>
{steps_list(CHECK_EXTENSIONS_STEPS)}
<pre class="code"><span class="k">const</span> _1: AdminApi.BlockName&lt;<span class="s">'sw-sales-channel-detail'</span>&gt; = <span class="s">'sw_sales_channel_detail_content_tab_theme'</span>; <span class="c">// .../sw-sales-channel-detail.html.twig:1</span></pre>
<div class="note"><p>The Storefront bundle overrides <code>sw_sales_channel_detail_content_tab_theme</code>.
That block exists only in the current flag state, so the check against <code>major.d.ts</code> fails.
This is a real result from the example snapshot.</p></div>

<h2>Limits and migration</h2>
<ul>
  <li>JS members are typed as <code>any</code>, so TypeScript checks names and argument counts, not value types.</li>
  <li>A typo in the target name falls back to the untyped signature. <code>admin:check-extensions</code> reports unknown targets.</li>
  <li>A computed override that returns <code>this.$super(…)</code> may need a return type (TS7023). We have not reproduced this yet.</li>
  <li>With the snapshot loaded, <code>override('&lt;known name&gt;', () =&gt; import('./plain'))</code> becomes a type error until the file uses <code>wrapOverrideConfig</code>. The upgrade notes have to say so.</li>
</ul>
"""
    lead = "The same snapshot is a type contract for plugins and the basis for a compatibility check against the current and the next major version."
    return lead, body


# --------------------------------------------------------------------------- Backtest

F20959 = """Break (8)
  block_moved_parent  component:sw-order-details-state-card/block:sw_order_detail_state_card_state_history_text  current, major
  element_crosses_block  component:sw-order-detail/crossing:div@sw_order_detail_header>sw_order_detail_header_label_manual_order  current, major
  block_scope_missing  component:sw-order-line-items-grid/block:sw_order_line_items_grid_grid_actions_delete/scope:itemIndex  current, major
    The template variable itemIndex from the actions slot is no longer available in block sw_order_line_items_grid_grid_actions_delete, so plugin overrides that use itemIndex fail at render time.
  block_scope_missing  …/block:sw_order_line_items_grid_grid_actions_delete_label/scope:itemIndex  current, major
  block_scope_missing  …/block:sw_order_line_items_grid_grid_actions_extension/scope:itemIndex  current, major
  block_scope_missing  …/block:sw_order_line_items_grid_grid_actions_show/scope:itemIndex  current, major
  block_scope_missing  …/block:sw_order_line_items_grid_grid_actions_show_label/scope:itemIndex  current, major
  method_signature_changed  component:sw-order-line-items-grid/method:onDeleteItem  current, major"""

F17339 = """Break (4)
  block_missing  component:sw-bulk-edit-custom-fields/block:sw_custom_field_set_renderer_card_form_renderer_change_field  major
    The block is gone, because the parent sw-custom-field-set-renderer no longer declares sw_custom_field_set_renderer_card_form_renderer, which this component overrides.
  block_missing  component:sw-bulk-edit-custom-fields/block:sw_custom_field_set_renderer_card_form_renderer_custom_field  major
  block_missing  component:sw-mail-template-index/block:sw_mail_template_list_tabs_header_footer  major
    Possible rename: sw_mail_template_list_mt_tabs.
  block_missing  component:sw-mail-template-index/block:sw_mail_template_list_tabs_templates  major"""

F15271 = f"""Public API check passed: no breaks.

Private ({PRIVATE_15271})
  block_added  component:sw-admin-menu-item/block:sw_admin_menu_item_expand_indicator_fold  current, major
    A new block of a private component, so it is not public API and plugins cannot rely on it.
  …"""


def chips(**counts):
    cls = {"Break": "t-break", "Addition": "t-add", "Private": "", "Info": "t-info"}
    if not counts:
        return '<span class="tag t-ok">no finding</span>'
    return '<div class="chips">' + "".join(f'<span class="tag {cls[k]}">{k} {v}</span>' for k, v in counts.items()) + "</div>"


def backtest():
    body = f"""
<p>We wrote the expected results by hand from the real commits. The generator has to reproduce them exactly.</p>

<h2>Historical cases</h2>
<div class="tw"><table><thead><tr><th>PR</th><th>What happened</th><th>Expected result</th></tr></thead><tbody>
<tr><td><b>#20959</b></td><td>Order detail polish. Plugins broke after the merge, fixed in #21189.</td><td>{chips(**load_case("20959")["groups"])}<span class="small muted">five of the breaks come from the block context rules: the actions slot lost <code>itemIndex</code></span></td></tr>
<tr><td><b>#17339</b></td><td>sw-tabs replaced by mt-tabs behind the major flag. The bulk edit lost its custom field checkboxes, and nobody noticed for four weeks.</td><td>{chips(**load_case("17339")["groups"])}<span class="small muted">all breaks in the major state only</span></td></tr>
<tr><td><b>#15271</b></td><td>UI shell rework, blocks of the admin menu deleted.</td><td>{chips(**load_case("15271-ui-shell")["groups"])}<span class="small muted">the shell components are @private</span></td></tr>
<tr><td><b>#17081</b></td><td>Small bug fix in the number range detail. Negative control.</td><td>{chips(**load_case("17081")["groups"])}<span class="small muted">identical snapshot</span></td></tr>
</tbody></table></div>
<p>Each case folder below <code>scripts/generate-public-api/__fixtures__/history/</code> has the commits in <code>case.json</code>, the expected report diffs, <code>findings.txt</code> and the HTML page.</p>

<h3>#20959 expected breaks</h3>
{code(F20959, "plain", wrap=True)}
<h3>#17339 expected breaks</h3>
{code(F17339, "plain", wrap=True)}
<h3>#15271 expected result</h3>
{code(F15271, "plain", wrap=True)}

<h2>Test fixtures</h2>
<p>Each fixture has a minimal source tree before and after the change, the expected snapshot for both, and the expected <code>findings.txt</code>.
Fixtures that are still missing are the rows with a gap on the Coverage page.</p>
<div class="tw"><table><thead><tr><th>Fixture</th><th>Change</th><th>Expected</th><th>Finding types</th></tr></thead><tbody>
<tr><td><code>code-inside-method</code></td><td>Code inside a method changes</td><td>{chips()}</td><td>B1</td></tr>
<tr><td><code>attributes-inside-block</code></td><td>Attributes, text, new elements inside a block; one class renamed</td><td>{chips(Info=1)}</td><td>B2, B47</td></tr>
<tr><td><code>addition</code></td><td>New prop, block, method, route and snippet key</td><td>{chips(Addition=5)}</td><td>B26</td></tr>
<tr><td><code>member-removed</code></td><td>Method renamed, computed removed</td><td>{chips(Break=2, Addition=1)}</td><td>B6, B36</td></tr>
<tr><td><code>route-snippet-removed</code></td><td>Route and snippet key removed</td><td>{chips(Break=2)}</td><td>B13, B16</td></tr>
<tr><td><code>service-store-member-removed</code></td><td>Service member and store action removed</td><td>{chips(Break=2)}</td><td>B18</td></tr>
<tr><td><code>block-moved</code></td><td>A single block and a subtree moved</td><td>{chips(Break=2)}</td><td>B8</td></tr>
<tr><td><code>element-crosses-block</code></td><td>New element across two blocks</td><td>{chips(Break=1)}</td><td>B9</td></tr>
<tr><td><code>block-context</code></td><td>Block loses its loop variable and its <code>v-if</code> context, new <code>v-if</code> chain across blocks</td><td>{chips(Break=3, Info=1)}</td><td>B39, B40, B41, B47</td></tr>
<tr><td><code>refs-and-classes</code></td><td>Template ref removed, ref moved to another component, class removed</td><td>{chips(Break=2, Info=1)}</td><td>B42, B43, B47</td></tr>
<tr><td><code>block-renamed-major-only</code></td><td>Block renamed only with the major flag</td><td>{chips(Break=1, Addition=1)}</td><td>B7, B38</td></tr>
<tr><td><code>prop-changes</code></td><td>Props removed, renamed, now required</td><td>{chips(Break=3, Addition=1)}</td><td>B6, B10</td></tr>
<tr><td><code>method-signature</code></td><td>Required parameter added, sync to async, optional parameter added</td><td>{chips(Break=2, Addition=1)}</td><td>B12, B27</td></tr>
<tr><td><code>deprecated-removal</code></td><td>Announced removal in that major</td><td><div class="chips"><span class="tag t-allowed">Allowed 4</span><span class="tag t-info">Info 1</span></div></td><td>B28, B47</td></tr>
<tr><td><code>deprecated-removal-too-early</code></td><td>The same removal one version too early</td><td>{chips(Break=4, Info=1)}</td><td>B28, B47</td></tr>
<tr><td><code>private-change</code></td><td>Changes inside a @private unit</td><td>{chips(Private=4)}</td><td>B30</td></tr>
<tr><td><code>public-becomes-private</code></td><td>Public component becomes @private</td><td>{chips(Break=1)}</td><td>B25</td></tr>
<tr><td><code>snippet-signature</code></td><td>Placeholder renamed, plural forms changed</td><td>{chips(Break=2)}</td><td>B17</td></tr>
<tr><td><code>referenced-type</code></td><td>Member of a referenced type removed</td><td>{chips(Break=1)}</td><td>B21</td></tr>
<tr><td><code>native-sfc</code></td><td>Native SFC: public binding removed, block renamed</td><td>{chips(Break=2, Addition=1)}</td><td>B23</td></tr>
<tr><td><code>accepted-break</code></td><td>Intended break with an acceptance entry</td><td><span class="tag t-accepted">Accepted 1</span></td><td>B29</td></tr>
<tr><td><code>acceptance-stale</code></td><td>Acceptance entry without a matching finding</td><td>{chips(Info=1)}</td><td>B35</td></tr>
</tbody></table></div>

<h2>Done criteria</h2>
<p>The generator prototype is done when all of these pass:</p>
<ul>
  <li>All known breaks of the historical cases are reported, and #17081 has no finding.</li>
  <li>Renaming a block, a method and a prop that the Storefront bundle uses fails the check each time.</li>
  <li>Two runs on the same commit produce byte-identical output.</li>
  <li>A PR that only regenerates the snapshot still fails the break check.</li>
  <li>Fewer than 5% false alarms on about 50 merged Administration PRs, judged together with an Administration lead.</li>
</ul>
"""
    lead = "Four real PRs and 22 hand-written fixtures define what the check must report. They are the acceptance test for the generator."
    return lead, body


# --------------------------------------------------------------------------- Decisions

DECISION_GROUPS = [
    ("What is public", [
        ("D1", "Everything without <code>@private</code> is public.", "The rule is already documented. Requiring <code>@public</code> instead would make almost everything private, because only about 50 items carry it."),
        ("D2", "Private parts are in the same files, marked, and never a break.", "Reviewers still see private changes, without a second set of files."),
        ("D13", "<code>@private</code> on a component or mixin export makes the whole unit private, including its name and blocks.", "This is how authors use the export tag today."),
    ]),
    ("Scope", [
        ("D3", "Reviewers and plugins are equally important.", "The snapshot has to produce readable diffs and a usable type contract from the start."),
        ("D4", "The Shopware object, components, blocks, routes, snippets, position identifiers and data sets.", "Plugins use all of them as strings."),
        ("D9", "The whole Administration.", "A partial snapshot gives false safety."),
        ("D16", "Referenced source types get their own copy.", "Otherwise a change to a shared type would be invisible."),
        ("D17", "Native setup SFCs from the start.", "They are coming, and adding them later costs more."),
        ("D19", "Snippet keys with placeholders and plural forms, never texts.", "A renamed placeholder breaks plugin <code>$t</code> calls; texts change too often to track."),
    ]),
    ("Format", [
        ("D5", "<code>.d.ts</code> files plus an <code>.api.md</code> report.", "TypeScript can check the <code>.d.ts</code> files; the report gives readable diffs. JSON alone could do neither well."),
        ("D6", "Two flag states, current and major, both committed and compared.", "Breaks behind the major flag (#17339) are invisible in a single snapshot."),
        ("D15", "Keep <code>index.json</code> as a machine index.", "Vite, Jest and ESLint need a cheap lookup without loading TypeScript."),
        ("D22", "The report is an outline without tree characters; the check adds a grouped summary and annotations.", "Tree characters change on every move and make diffs noisy."),
        ("D25", "Components record template refs and static CSS classes. A missing or retargeted ref is a break, a missing class is info.", "635 store plugins read core <code>$refs</code>, 1,541 style core classes; classes change too often to block on."),
        ("D23", "Flag state markers name the version: <code>[until v6.8.0]</code> and <code>[from v6.8.0]</code>.", "<code>[current only]</code> loses its meaning in an old commit, a version does not."),
    ]),
    ("CI and review", [
        ("D7", "A drift check and a break check that runs the target branch's generator on base and head.", "Regenerating the snapshot or changing the generator cannot hide a break."),
        ("D11", "A simple HTML page per check run, without simulating plugin overrides.", "A simulation would be costly and still incomplete."),
        ("D14", "Intended breaks go into <code>accepted-breaks.json</code> with a justification, approved by code owners.", "A PR label leaves no reason and no review trail."),
        ("D18", "Elements that already cross blocks on trunk are the baseline.", "Otherwise every PR would fail on old crossings."),
    ]),
    ("Plugins and existing tools", [
        ("D8", "The snapshot ships in the Administration package and is used by <code>admin-types.d.ts</code> and <code>admin:check-extensions</code>.", "Plugins already use both."),
        ("D10", "Replace the four generated lists directly.", "Two sources of truth would drift apart."),
        ("D20", "Core types <code>Component.override</code> as an interface; the snapshot adds a typed signature.", "Plugins keep their code; a cast or a new function would force changes everywhere."),
        ("D21", "A helper <code>wrapOverrideConfig</code> for override files loaded lazily.", "Most large plugins load overrides lazily."),
        ("D26", "Filters, directives, ACL privileges, settings groups, CMS, rule conditions, search types, flow actions and custom field types that core registers are units.", "Hundreds of store plugins use these names; a removal breaks them silently."),
        ("D27", "Event bus events and feature flags are units. A missing event is a break, a missing flag is info.", "Plugins listen to core events and branch on core flags."),
        ("D28", "SCSS variables and mixins are units. A missing name is a break, a changed value is info.", "691 store plugins import <code>~scss/variables</code>; a missing variable fails the plugin build."),
        ("D29", "<code>admin:check-extensions</code> checks the new names and warns about <code>$parent</code> and imports from <code>src/</code>.", "These uses cannot be typed, but plugin developers should know the risk."),
        ("D30", "A store usage index annotates findings and detects name collisions (proposal).", "Counts make findings concrete and measure false alarms."),
        ("D24", "Blocks record their context: whether they follow an open <code>v-if</code> chain, and which <code>v-for</code> and slot variables they can use. <code>v-if</code> chains across blocks count as crossings.", "Plugin templates continue core <code>v-if</code> chains and use loop and slot variables; #20959 removed <code>itemIndex</code> from a slot."),
    ]),
]


def decisions():
    out = ["<p>Decision 12 was a working agreement for the branch, not a design decision, and is left out.</p>"]
    for title, rows in DECISION_GROUPS:
        out.append(f"<h2>{E(title)}</h2>")
        out.append('<div class="tw"><table><thead><tr><th>ID</th><th>Decision</th><th>Why</th></tr></thead><tbody>')
        for i, d, why in rows:
            out.append(f'<tr id="{i}"><td class="id"><a href="#{i}">{i}</a></td><td>{d}</td><td class="muted">{why}</td></tr>')
        out.append("</tbody></table></div>")
    out.append("""
<h2>Assumptions</h2>
<p>We assumed these without asking. Correct them if they are wrong.</p>
<div class="tw"><table><thead><tr><th>ID</th><th>Assumption</th></tr></thead><tbody>
<tr id="S1"><td class="id"><a href="#S1">S1</a></td><td>Only the core Administration is generated. The Storefront bundle's Administration extension and installed plugins serve as real consumers when we verify the generator.</td></tr>
<tr id="S2"><td class="id"><a href="#S2">S2</a></td><td>A removal in the major that a <code>@deprecated tag:vX</code> announced is not a break. Only tags in the source count, not entries in <code>UPGRADE-*.md</code>.</td></tr>
<tr id="S3"><td class="id"><a href="#S3">S3</a></td><td>The folder layout and the split into one unit per component or module are proposed together with the golden files.</td></tr>
</tbody></table></div>

<h2>Prior art</h2>
<div class="tw"><table><thead><tr><th>Tool</th><th>What we took from it</th></tr></thead><tbody>
<tr><td>Microsoft API Extractor</td><td>A committed, readable <code>.api.md</code> report per package that changes in the PR diff.</td></tr>
<tr><td>GraphQL Inspector</td><td>Grouping changes into breaking, dangerous and safe, which became our finding groups.</td></tr>
<tr><td>Go <code>api/*.txt</code></td><td>One line per API element, so diffs stay line-based.</td></tr>
<tr><td>roave/backward-compatibility-check</td><td>The PHP side already compares base and head this way.</td></tr>
</tbody></table></div>
""")
    lead = "What we decided so far and why. Refer to D and S numbers in your feedback."
    return lead, "\n".join(out)


# --------------------------------------------------------------------------- Open questions

OPEN_BLOCKING = [
    ("Q1", "Public children of private bases", "A public component that extends a <code>@private</code> one (about 17 cases, for example <code>sw-bulk-edit-*</code> on <code>sw-custom-field-set-renderer</code>): are the blocks it inherits public?",
     "Yes. Plugins override them by the child's name, as in #17339. A change in the private base that removes an inherited block is a break on the public child.", "A15"),
    ("Q3", "Private functions that every built plugin calls", "<code>overrideComponentSetup</code>, <code>registerNativeExtensionTargets</code>, <code>registerOverrideComponent</code>, <code>attachOverrides</code> and <code>getExposedProps</code> are marked <code>@private</code>, but compiled plugin bundles call them.",
     "A new tag for these functions: changes to them are breaks, but the name helpers do not offer them to plugin code.", "A33"),
    ("Q4", "Removed trailing parameter", "Is removing the last parameter of a method always a break? In #20959 the template stopped passing <code>itemIndex</code>, and overrides that read it got <code>undefined</code>.",
     "Yes, always a break. An override can read the parameter even when core no longer uses it.", "B12"),
    ("Q6", "Finding texts", "The fixtures use explanations and fix hints for each element kind that the spec does not define.",
     "One text template per finding id, with one sentence per element kind, kept in a table in <code>FORMAT.md</code>.", "B6, B18, B26"),
    ("Q7", "Missing finding ids", "<code>method_optional_param_added</code>, <code>service_member_missing</code>, <code>store_member_missing</code> and <code>setup_missing</code> are used but not defined. A position identifier that moves to another host has no finding.",
     "Add the four ids to the table, and add <code>position_id_moved</code> as a break.", "A18, B18, B23, B27"),
    ("Q11", "Still not covered", "CSS classes, directives, filters and ACL privileges are now covered (A47–A50). Should Meteor components or Extension SDK locations follow?",
     "Leave both out for now: Meteor components have their own versioning, and only 11 store plugins use the SDK.", "N11, N12"),
    ("Q14", "Private API that store plugins use", "<code>Shopware.Application</code> (601 store plugins, <code>getContainer</code> 520), <code>Component.getComponentRegistry</code> (94), <code>Component.build</code> (16) and <code>Shopware.Utils.EventBus</code> (59) are <code>@private</code>.",
     "Make <code>Application.getContainer</code>, <code>addServiceProvider</code>, <code>addServiceProviderDecorator</code> and the EventBus public, because plugins have no alternative. Keep the component registry private and report its use through the store usage index.", "A33, A55, B30"),
    ("Q15", "Store usage index in CI", "Where does the break check get the store usage counts from? The mirror is internal.",
     "A scheduled internal job writes the counts per element id to <code>public-api/store-usage.json</code>; the break check only reads that file.", "B46"),
    ("Q12", "Core overrides of native components", "How do <code>swDefineOverride</code> and <code>&lt;sw-block extends&gt;</code> in core appear in the snapshot?",
     "As entries in <code>ComponentOverrides</code>, like Options API overrides, with their setup keys and blocks.", "A25"),
    ("Q13", "Version rule for deprecations", "How is <code>tag:v6.8.0</code> compared with the head version <code>6.8</code>, and does the rule apply per flag state?",
     "Compare major and minor only. Apply the rule per flag state, so a removal in the major state is allowed when the tag names that major.", "B28"),
]

OPEN_LATER = [
    ("Q2", "Migration to native SFCs", "When a component moves from the Options API to a native SFC, every way a plugin uses it changes: overrides, extends and references see different members. The migration codemod also writes <code>@private</code> on every migrated component without <code>@public</code>, which silently makes public components private.",
     "Report the migration as one info finding that lists the changed members, and keep the break rules for members that disappear. Fix the codemod so it keeps the visibility.", "A21–A25"),
    ("Q5", "Where the HTML page lives", "Can the HTML page be attached to the PR, or does it stay a workflow artifact?",
     "Workflow artifact, linked from the step summary.", ""),
    ("Q8", "CI runtime", "The break check runs the generator twice per PR. What runtime is acceptable?",
     "Under three minutes for both runs. Cache the base model per commit.", ""),
    ("Q9", "Twig parsing", "Extend the parser of the existing block list generator or write a new tokenizer?",
     "Start from the existing parser and extend it with nesting and element tracking.", ""),
    ("Q16", "Scope of classes", "Should <code>classes</code> list every static class or only the BEM root and element classes of the component?",
     "Every static class: plugins also style utility and state classes such as <code>has-warning</code>, and removals are only info.", "A47"),
    ("Q10", "Native SFC extraction", "Is <code>@vue/compiler-sfc</code> enough, or do we need <code>vue-component-meta</code>?",
     "<code>@vue/compiler-sfc</code> first; add <code>vue-component-meta</code> only if prop types need it.", ""),
]


def open_table(rows):
    out = ['<div class="tw"><table><thead><tr><th>ID</th><th>Question</th><th>Proposed answer</th><th>Related</th></tr></thead><tbody>']
    for i, title, q, answer, rel in rows:
        out.append(f'<tr id="{i}"><td class="id"><a href="#{i}">{i}</a></td><td><b>{title}</b><br><span class="muted">{q}</span></td><td>{answer}</td><td class="small">{rel}</td></tr>')
    out.append("</tbody></table></div>")
    return "".join(out)


def open_questions():
    body = f"""
<div class="note warn"><p>The proposed answers are suggestions, not decisions. Reply with "agree Q4" or with your own answer.</p></div>

<h2>Before the generator starts</h2>
<p>These change the spec, so they need an answer first.</p>
{open_table(OPEN_BLOCKING)}

<h2>Can be decided while building</h2>
{open_table(OPEN_LATER)}

<h2>Known gaps in the spec</h2>
<ul>
  <li>The type checks do not verify block parents or the block names of crossings, so <code>Block&lt;'sw_bogus'&gt;</code> compiles (A13, A16).</li>
  <li>The order of findings inside a summary group is only a convention in the fixtures: sorted by element id.</li>
  <li>The fixtures reference <code>_base</code>, which only exists in <code>public-api/</code>. The fixture test has to provide it.</li>
  <li>The <code>members</code> key of services is not in the printing rules (A34).</li>
</ul>
"""
    lead = "What still needs a decision, with a proposed answer for each question, and the known gaps in the spec."
    return lead, body


# --------------------------------------------------------------------------- Glossary

TERMS = [
    ("Snapshot", "Everything the generator writes below <code>public-api/</code>: one report and one <code>.d.ts</code> file per unit, plus entry points and <code>index.json</code>."),
    ("Unit", "One source item with its own snapshot files: a component, a module with its routes, a mixin, a snippet file, a service or a store."),
    ("Report (.api.md)", "The readable outline of a unit for reviewers. Shows both flag states in one file."),
    (".d.ts file", "The TypeScript version of a unit. Plugins compile against it; there is one per flag state."),
    ("Flag state: current and major", "Current: the major feature flag <code>v6.8.0.0</code> is off, as in today's releases. Major: the flag is on, as in the next major release. The snapshot is generated for both. Reports mark items of one state with <code>[until v6.8.0]</code> or <code>[from v6.8.0]</code>."),
    ("Golden files", "Hand-written expected output that the generator must reproduce: the example snapshot, the test fixtures and the historical cases."),
    ("Example snapshot", "The hand-written part of the snapshot on the branch: sw-sales-channel, sw-order, two mixins, the Shopware object, a service, a store, the shopware:* modules and referenced types."),
    ("Test fixture", "A minimal source tree before and after one kind of change, with the expected snapshot and findings."),
    ("Historical case", "A real PR replayed on its real commits, with the expected findings. Used as backtest."),
    ("Base and head", "The commit a PR starts from, and the last commit of the PR."),
    ("Drift check", "CI check that the committed snapshot matches the code."),
    ("Break check", "CI check that compares base and head and fails on breaks that are not announced or accepted."),
    ("Finding", "One classified difference between base and head, for example <code>block_missing</code>. Each finding has a group: break, addition, allowed, accepted, private or info."),
    ("Element id", "The stable name of an API element in findings and in <code>accepted-breaks.json</code>, for example <code>component:sw-order-detail/method:onSave</code>."),
    ("Accepted break", "A break the PR makes on purpose, listed with a justification in <code>accepted-breaks.json</code>."),
    ("Block", "A Twig <code>{% block %}</code> or a native <code>&lt;sw-block&gt;</code>. Plugins override blocks by name."),
    ("Extend child", "A component created with <code>Component.extend</code>. It inherits the blocks and members of its parent."),
    ("Crossing", "An HTML element that opens in one block and closes in another, or a <code>v-if</code> chain that continues in another block. Overriding the first block without <code>{% parent %}</code> breaks the markup or the chain."),
    ("Registry", "A service that collects named entries, such as CMS blocks or rule conditions. The snapshot records the entries core registers, not those of plugins."),
    ("Store usage index", "Proposal: counts per element id of how many store plugins use it, generated from the internal store plugin mirror. Annotates findings and detects name collisions."),
    ("Block context", "Two facts about the place of a block that overrides depend on: whether it follows an open <code>v-if</code> chain (<code>[after v-if]</code>), and which <code>v-for</code> and slot variables it can use (<code>[scope: …]</code>)."),
    ("$super target", "A method or computed property that an override can call with <code>this.$super('name')</code>."),
    ("Position identifier, data set", "Extension points of the Meteor Admin SDK in core templates."),
    ("Native SFC", "A component written as <code>.vue</code> file with <code>&lt;script setup&gt;</code> instead of the Options API."),
    ("Referenced type", "A TypeScript type from <code>src/</code> that a unit uses, copied into the snapshot so that changes to it show up."),
]


def glossary():
    items = "".join(f"<dt>{t}</dt><dd>{d}</dd>" for t, d in TERMS)
    body = f'<h2>Terms</h2><dl class="gloss">{items}</dl>'
    lead = "The terms these pages use, each with one meaning."
    return lead, body
