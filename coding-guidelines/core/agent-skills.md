# Agent Skills

How to add an AI Skill to this repository — file layout, the optional
unattended twin, and the conventions that keep two skills looking like two
skills, not two snowflakes.

A **Skill** packages an AI capability in the [Anthropic Agent Skills](https://agentskills.io/specification)
format. It auto-loads in Claude Code, opencode, Codex CLI, Cursor, Gemini
CLI and other Agent-Skills-compatible runtimes when the user message matches
the skill's `description`. Today this repository ships `triage` and
`bugfixer`; the same pattern applies to any future skill.

## Two surfaces per skill

Each skill has up to two surfaces — keep them in lockstep:

1. **Interactive** — `.claude/skills/<name>/SKILL.md`. Loaded into a developer's
   editor session. Emits whatever output format is most useful to a human
   (typically Markdown).
2. **Unattended (optional)** — a [GitHub Agentic Workflow](https://github.com/githubnext/gh-aw)
   at `.github/workflows/<name>.md` plus a `runtime-import`-ed policy fragment
   at `.github/aw/<name>-policy.md`. Emits through `safe-outputs`
   (`upload-artifact`, `create-pull-request`, `push-to-pull-request-branch`,
   `add-labels`, `add-comment`, `noop`, depending on the workflow).

When both surfaces exist, they share the same rubric under
`.github/aw/shared/<name>-policy.md` so they cannot drift in policy. Keep
interactive-only references under `.claude/skills/<name>/references/` when the
gh aw workflow does not need to import them.

## Prerequisite

Install the `gh aw` extension once per workstation, pinned to the version this
repo's `.github/aw/actions-lock.json` is built against. The canonical pin and
install command live in [`.github/aw/README.md`](../../.github/aw/README.md) →
"Pinning".

## File layout

```
.claude/skills/<name>/
├── SKILL.md                   # required — frontmatter + body
├── references/                # optional — on-demand context for the agent
│   ├── CLASSIFICATION.md
│   ├── DOMAINS.md
│   └── TOOLS.md
└── assets/                    # optional — worked examples, fixtures
    └── examples.md

.github/workflows/<name>.md    # optional — gh aw SOURCE (edit this)
.github/workflows/<name>.lock.yml   # compiled — `gh aw compile` regenerates
.github/workflows/agentics-maintenance.yml # generated when gh aw needs safe-output maintenance/replay
.github/aw/<name>-policy.md    # optional — gh-aw-mode-specific fragment,
                               # runtime-imported by the workflow
.github/aw/shared/<name>-policy.md  # optional — shared rubric loaded by
                                    # both the interactive skill and the
                                    # gh aw policy fragment
```

`.github/aw/actions-lock.json` and `.github/aw/logs/` are shared across all
skills — never per-skill.

## Adding a new skill — checklist

1. **Skill body.** Create `.claude/skills/<name>/SKILL.md` with at minimum
   `name` and `description` in the frontmatter (see the
   [Agent Skills spec](https://agentskills.io/specification)). Keep SKILL.md
   short; push detail into `references/`.

2. **References.** Move anything load-bearing but stable into
   `references/<TOPIC>.md`. The agent loads them on demand; they keep
   SKILL.md scannable.

   **If you build both an interactive surface and an unattended twin,**
   the shared policy must live under `.github/aw/shared/<name>-policy.md`,
   not inside `.claude/skills/<name>/references/`. gh aw's runtime-import
   security validation forbids importing files outside `.github/`. The
   interactive skill references the same file via its repo-root path; the
   gh aw policy fragment imports it via
   <code v-pre>{{#runtime-import .github/aw/shared/&lt;name&gt;-policy.md}}</code>. See how the
   `triage` skill wires it up for the exact pattern.

3. **Decide on the unattended path.** If the skill should also run in CI:
   create `.github/workflows/<name>.md` (gh aw frontmatter) plus
   `.github/aw/<name>-policy.md` (frontmatter-free fragment, runtime-imported
   by the workflow), then `gh aw compile`. The mechanics — secrets remap,
   engine model pin, registration trick, output validation — live in
   [`.github/aw/README.md`](../../.github/aw/README.md).

4. **Update the catalogue.** Add a row to `.claude/skills/README.md`
   describing the trigger phrases and the deliverable.

5. **Run it once.** `gh aw run <name> -f …` and inspect with
   `gh aw audit <run-id>`.

## Skill-specific conventions

- **Frontmatter `description` is matched against user messages** in the
  interactive surface. Be specific about trigger phrases — they decide whether
  the skill auto-loads.
- **References load on demand.** Keep SKILL.md scannable; push lookups,
  taxonomies, and tool catalogues into `references/`.
- **One model across workflows.** All gh aw workflows in this repo pin the
  same `engine.model` (currently `claude-sonnet-4-6`). Deviate only with a
  concrete reason and document it in the workflow source comment.

## Reference docs

- [`.github/aw/README.md`](../../.github/aw/README.md) — gh aw setup,
  pinning, secrets, registration, output validation.
- [`.claude/skills/README.md`](../../.claude/skills/README.md) — interactive
  skill catalogue.
- [`gh aw` Reference](https://github.github.com/gh-aw/) — upstream docs.
- [Agent Skills specification](https://agentskills.io/specification) — the
  SKILL.md frontmatter contract.
