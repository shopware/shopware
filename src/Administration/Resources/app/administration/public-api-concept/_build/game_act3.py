"""Act 3 of play.html: the same job with the snapshot, as reviewer, PR author and plugin developer."""
from fixtures import load_case, report_excerpt
from game_ui import E, clock, diff_view, merge_block_quiz, nav, pr_card, quiz, splash, summary_view
from render import ts

# Verdict lines follow FORMAT.md. Each carries the status it is shown with.
VERDICTS = {
    "pass": ("Public API check passed: no breaks.", "pass"),
    "accepted": ("Public API check passed: 1 break accepted in public-api/accepted-breaks.json.", "pass"),
    "fail": ("Public API check failed: 1 unannounced break.", "fail"),
}

# Per Act 1 case: which .api.md diff the reviewer sees in the replay.
REPLAY = {
    "20959": {"state": "current", "path": "module/sw-order/page/sw-order-detail.api.md", "first": "sw_order_detail_header_title_new", "last": "<div>"},
    "17339": {"state": "major", "path": "module/sw-bulk-edit/component/sw-bulk-edit-custom-fields.api.md", "first": "blocks", "last": "mt_tabs_form_renderer"},
    "15271-ui-shell": {"state": "current", "path": "app/component/structure/sw-admin-menu.api.md", "first": "sw_admin_menu_footer", "last": "sw_admin_menu_user_actions_items"},
}


def option(choice, label, grade, verdict, note):
    return {"choice": choice, "label": label, "grade": grade, "verdict": verdict, "note": note}


AUTHOR = [
    {
        "case": "20959",
        "element": "component:sw-order-line-items-grid/method:onDeleteItem/param:itemIndex",
        "context": "You removed the second parameter of <code>onDeleteItem</code>. The template now calls <code>onDeleteItem(item)</code>.",
        "options": [
            option("keep", "Keep <code>itemIndex</code>, keep passing it while <code>v6.8.0.0</code> is off, and mark the method <code>@bc-change ParameterRemoval</code>", "best", "pass",
             "The pattern \"Parameter removal\". Plugins that read the second argument keep working, <code>admin:check-extensions</code> shows them the change before 6.8, and deleting the parameter in 6.8 is allowed."),
            option("accept", "Add an entry to <code>accepted-breaks.json</code> with a justification", "fail", "accepted",
             "The check would pass, but the framework team rejects the entry: the pattern \"Parameter removal\" covers this change, and an entry has to say why the pattern cannot be followed. Here it can."),
            option("private", "Mark <code>onDeleteItem</code> <code>@private</code>", "fail", "fail",
             "<code>visibility_became_private</code>: a public method that becomes private is a break too. Deprecate first, make it private in the major."),
            option("ignore", "Push anyway, the reviewer will decide", "fail", "fail", "The check blocks the merge until the break is fixed or accepted."),
        ],
    },
    {
        "case": "20959",
        "element": "component:sw-order-line-items-grid/computed:paginatedLineItems",
        "context": "You added the computed property <code>paginatedLineItems</code>. Only the grid's own pagination uses it.",
        "options": [
            option("private", "Mark it <code>@private</code>", "best", "pass", "It moves to the private group. You can rename or remove it later without a deprecation."),
            option("public", "Leave it public", "ok", "pass", "Additions never block. But plugins may rely on it from now on, so changing it later needs a deprecation first."),
        ],
    },
    {
        "case": "17339",
        "element": "component:sw-mail-template-index/block:sw_mail_template_list_tabs_templates",
        "context": "You replaced <code>sw-tabs</code> with <code>mt-tabs</code> behind the major flag. In the major branch, the old tab blocks are gone.",
        "options": [
            option("deprecate", "Keep the block in the major branch, even empty, and mark it <code>{# @deprecated tag:v6.8.0 #}</code>", "best", "pass",
             "The pattern \"Removal\". The removal is announced in the source, and <code>admin:check-extensions</code> warns plugins that still use the block."),
            option("accept", "Add an entry to <code>accepted-breaks.json</code> with a justification", "fail", "accepted",
             "The check would pass, but the framework team rejects the entry: the pattern \"Removal\" covers this block. <code>accepted-breaks.json</code> is for breaks that no pattern can avoid, such as a security fix."),
            option("flag", "Leave it, the flag is off in today's releases", "fail", "fail",
             "The check compares both flag states. In the major state the block is gone, so it is a break, like the bulk edit in round 2."),
        ],
    },
]

# The plugin is made up. Each error is based on a real finding or on the rename example of FORMAT.md.
PLUGIN = [
    {
        "file": "src/extension/sw-sales-channel-detail/index.ts",
        "lang": "ts",
        "code": """Shopware.Component.override('sw-sales-channel-detail', {
    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = this.$super('getLoadSalesChannelCriteria');
            criteria.addAssociation('swagExports');
            return criteria;
        },
    },
});""",
        "line": 4,
        "fixed": ("src/extension/sw-sales-channel-detail/index.ts", """Shopware.Component.override('sw-sales-channel-detail', {
    methods: {
        getSalesChannelCriteria() {
            const criteria = this.$super('getSalesChannelCriteria');
            criteria.addAssociation('swagExports');
            return criteria;
        },
    },
});"""),
        "source": "editor · current, major",
        "message": "'getLoadSalesChannelCriteria' is not a method of sw-sales-channel-detail. Possible rename: getSalesChannelCriteria.",
        "fixes": [
            ("Rename the override and the <code>$super</code> call to <code>getSalesChannelCriteria</code>", "best",
             "Fixed. The check's rename hint pointed you there, before any merchant noticed."),
            ("Cast <code>this</code> to <code>any</code>", "fail", "The editor is quiet, but <code>$super</code> throws at runtime, because the method is gone."),
        ],
    },
    {
        "file": "src/extension/sw-mail-template-index/sw-mail-template-index.html.twig",
        "lang": "twig",
        "code": """{% block sw_mail_template_list_tabs_templates %}
    {% parent %}
    <sw-tabs-item :route="{ name: 'swag.mail.archive' }">
        Archive
    </sw-tabs-item>
{% endblock %}""",
        "line": 1,
        "fixed": ("src/extension/sw-mail-template-index/index.ts", """Shopware.Component.override('sw-mail-template-index', {
    computed: {
        mailTemplateTabs() {
            return [
                ...this.$super('mailTemplateTabs'),
                {
                    label: 'Archive',
                    name: 'swag.mail.archive',
                    onClick: () => this.$router.push({ name: 'swag.mail.archive' }),
                },
            ];
        },
    },
});"""),
        "source": "admin:check-extensions · major",
        "finding": ("17339", "component:sw-mail-template-index/block:sw_mail_template_list_tabs_templates"),
        "fixes": [
            ("Override the new computed <code>mailTemplateTabs</code> and add your tab to it", "best",
             "Fixed. <code>mt-tabs</code> renders its tabs from <code>mailTemplateTabs</code>, which the same PR added as public API. The check listed it as an addition."),
            ("Keep the block override", "fail", "It renders nothing in 6.8. The block only exists until v6.8.0."),
        ],
    },
    {
        "file": "src/extension/sw-order-line-items-grid/index.ts",
        "lang": "ts",
        "code": """Shopware.Component.override('sw-order-line-items-grid', {
    methods: {
        onDeleteItem(item, itemIndex) {
            this.swagLogDeletion(itemIndex);
            return this.$super('onDeleteItem', item, itemIndex);
        },
    },
});""",
        "line": 5,
        "fixed": ("src/extension/sw-order-line-items-grid/index.ts", """Shopware.Component.override('sw-order-line-items-grid', {
    methods: {
        onDeleteItem(item) {
            this.swagLogDeletion(item.id);
            return this.$super('onDeleteItem', item);
        },
    },
});"""),
        "source": "editor · current, major",
        "finding": ("20959", "component:sw-order-line-items-grid/method:onDeleteItem/param:itemIndex"),
        "message": "$super('onDeleteItem') expects 1 argument, but got 2.",
        "fixes": [
            ("Log <code>item.id</code> instead and call <code>$super('onDeleteItem', item)</code>", "best",
             "Fixed, and it works before and after the update. Core calls <code>onDeleteItem(item)</code> now, so <code>itemIndex</code> was always undefined."),
            ("Keep it, <code>itemIndex</code> is optional", "fail", "Core calls <code>onDeleteItem(item)</code> now. Your log has written <code>undefined</code> since the update."),
        ],
    },
]


def find(case, element):
    return next(f for f in load_case(case)["findings"] if f["element"] == element)


ROLES = [
    ("◎", "Reviewer", "The same three PRs as in Act 1. This time you only get what the check prints. Beat your Act 1 time."),
    ("✎", "PR author", "CI annotates your line before anyone reviews it. Pick the fix."),
    ("⧉", "Plugin developer", "Upgrade day. First the way it goes today, then with the snapshot."),
]


def role_splash(n):
    icon, title, mission = ROLES[n]
    eyebrow = f"Act 3 · With the snapshot · part {n + 1} of {len(ROLES)}"
    return splash(f"Act 3 · {title}", eyebrow, icon, title, mission, [name for _, name, _ in ROLES], n)


def replay_screen(i, r, replay):
    data = load_case(r["case"])
    breaks = [f'<div class="fline"><b>{E(f["kind"])}</b> <span>{E(f["element"])}</span></div>' for f in data["findings"] if f["group"] == "Break"]
    controls, result = merge_block_quiz("Does this PR break a plugin?", r["answer"], "The first line of the summary already says it.")
    memory = '<p class="sub">You saw this PR in Act 1, so part of the speedup is memory. The first line of the summary answers the question on its own, though.</p>' if i == 0 else ""
    return f"""
<section class="screen quiz" data-group="replay" data-compare="act1" data-round="{i}" data-act="Act 3 · Reviewer" hidden>
  <h2 class="kicker" tabindex="-1">Reviewer · replay {i + 1} of {len(REPLAY)} {clock()}</h2>
  {pr_card(data['meta'], r['stat'])}
  <div class="lbl">Check summary</div>
  {summary_view(data, breaks)}
  <div class="fname">public-api/{E(replay['path'])} · {E(replay['state'])} state</div>
  {diff_view(report_excerpt(r['case'], replay['state'], replay['path'], replay['first'], replay['last']), small=True)}
  {controls}
  <div class="reveal" hidden>
    {result}
    <p class="timing">Act 1: <b class="t-was">–</b> · Now: <b class="t-now">–</b></p>
    {memory}
    {nav()}
  </div>
</section>"""


def annotation_view(f):
    """The CI annotation an author sees on the source line: errors for breaks, notices for the rest."""
    detail = {d.split(": ", 1)[0]: d for d in f["details"] if d.startswith(("At: ", "Fix: "))}
    reason = next(d for d in f["details"] if d not in detail.values())
    level, icon = ("error", "⚠") if f["group"] == "Break" else ("notice", "ℹ")
    at = detail["At"][4:]
    return f'<div class="annot {level}"><div class="ah">{icon} {E(f["kind"])} <span>{E(at)}</span></div><p>{E(reason)}</p><p class="fix">{E(detail.get("Fix", ""))}</p></div>'


def verdict_note(key, note):
    verdict, status = VERDICTS[key]
    return f'<span class="term inline"><span class="headline {status}">{E(verdict)}</span></span> {note}'


def author_screen(i, a):
    options = [(o["choice"], str(n + 1), o["label"], o["grade"]) for n, o in enumerate(a["options"])]
    notes = {o["choice"]: verdict_note(o["verdict"], o["note"]) for o in a["options"]}
    controls, result = quiz("What do you do?", options, {"best": "Best fix", "ok": "Works", "fail": "Blocked"}, notes)
    return f"""
<section class="screen quiz" data-group="author" data-act="Act 3 · PR author" hidden>
  <h2 class="kicker" tabindex="-1">PR author · {i + 1} of {len(AUTHOR)}</h2>
  <p class="sub">{a['context']} Assume your PR contains only this change.</p>
  {annotation_view(find(a["case"], a["element"]))}
  {controls}
  <div class="reveal" hidden>
    {result}
    {nav(("index.html#one-change-end-to-end", "how an author resolves a break") if i == len(AUTHOR) - 1 else None)}
  </div>
</section>"""


def plugin_story_screen():
    beats = [
        ("09:00", "You bump Shopware in your plugin's dev setup. The build is green."),
        ("09:20", "Your tests pass. They mock core components, so they never render core's templates."),
        ("10:00", "You release the update."),
        ("After 6.8", "A merchant reports that your mail archive tab is gone, and your deletion log says undefined."),
    ]
    items = "".join(f'<li><b>{E(t)}</b><span>{E(text)}</span></li>' for t, text in beats)
    return f"""
<section class="screen" data-act="Act 3 · Plugin developer" hidden>
  <h2 class="kicker" tabindex="-1">Plugin developer · upgrade day, today</h2>
  <p class="sub">Your plugin <b>SwagOrderTools</b> hooks into three core components. Here is how an upgrade goes without the snapshot:</p>
  <ol class="timeline">{items}</ol>
  <p class="sub">That is how the bulk edit break of #17339 surfaced: four weeks after the merge. Same day again, with the snapshot.</p>
  {nav(next_label="Replay upgrade day")}
</section>"""


def editor(path, code, lang, err_line=None):
    highlight = ts if lang == "ts" else E
    body = "".join(f'<span class="ln{" err-line" if n == err_line else ""}">{highlight(line)}</span>' for n, line in enumerate(code.split("\n"), 1))
    return f'<div class="fname">{E(path)}</div><pre class="diff small code-ts editor">{body}</pre>'


def plugin_error(i, p):
    finding = find(*p["finding"]) if "finding" in p else None
    message = p.get("message") or finding["details"][0]
    based = f'Based on {finding["kind"]} in #{p["finding"][0]}' if finding else "Based on the rename example in FORMAT.md"
    fixed_file, fixed_code = p["fixed"]
    fixes = "".join(
        f'<button class="btn fix g-{grade}" data-grade="{grade}">{label}</button><span class="fix-note" hidden>{note}</span>'
        for label, grade, note in p["fixes"]
    )
    return f"""<div class="err" data-err="{i}">
    <div class="before">{editor(p['file'], p['code'], p['lang'], p['line'])}</div>
    <div class="after">{editor(fixed_file, fixed_code, "ts")}</div>
    <div class="problem"><div class="ph"><span class="dot"></span>{E(p['source'])}<span class="based">{E(based)}</span></div><p>{E(message)}</p></div>
    <div class="fixes">{fixes}</div>
  </div>"""


def plugin_editor_screen():
    errors = "".join(plugin_error(i, p) for i, p in enumerate(PLUGIN))
    return f"""
<section class="screen fixer" data-group="plugin" data-act="Act 3 · Plugin developer" hidden>
  <h2 class="kicker" tabindex="-1">Plugin developer · upgrade day, with the snapshot</h2>
  <p class="sub">Your editor checks the plugin against <code>current.d.ts</code> and <code>major.d.ts</code>, and <code>admin:check-extensions</code> covers the Twig file. Fix all three before you release.</p>
  <p class="illus">Illustrative: the plugin and the message wording are made up. The breaks are real. Type errors in <code>.ts</code> files need the core change D20.</p>
  {errors}
  <p class="gate"><span class="fixed-count">0</span>/{len(PLUGIN)} fixed</p>
  {nav(("plugins.html#admin-check-extensions", "admin:check-extensions"), disabled=True)}
</section>"""


def act3_screens(rounds):
    replays = "".join(replay_screen(i, r, REPLAY[r["case"]]) for i, r in enumerate(rounds))
    authors = "".join(author_screen(i, a) for i, a in enumerate(AUTHOR))
    plugin = plugin_story_screen() + plugin_editor_screen()
    return role_splash(0) + replays + role_splash(1) + authors + role_splash(2) + plugin

