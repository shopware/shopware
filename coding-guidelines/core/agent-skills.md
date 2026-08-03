# Agent Skills

How to add an AI Skill to this repository — file layout, the optional
unattended twin, and the conventions that keep two skills looking like two
skills, not two snowflakes.

A **Skill** packages an AI capability in the [Anthropic Agent Skills](https://agentskills.io/specification)
format. It is offered to Claude Code, opencode, Codex CLI, Cursor, Gemini
CLI and other Agent-Skills-compatible runtimes. Skills normally match their
`description` against the user message (best-effort, model-decided); skills
with unattended CI twins require explicit invocation. This repository ships
several skills; the same pattern applies to any new skill.

## Two surfaces per skill

Each skill has up to two surfaces — keep them in lockstep:

1. **Interactive** — `.agents/skills/<name>/SKILL.md`. Loaded into a developer's
   editor session. `.claude/skills` points to the same directory for Claude
   Code compatibility. Emits whatever output format is most useful to a human
   (typically Markdown).
2. **Unattended (optional)** — a [GitHub Agentic Workflow](https://github.com/githubnext/gh-aw)
   at `.github/workflows/<name>.md` plus a `runtime-import`-ed policy fragment
   at `.github/aw/<name>-policy.md`. Emits through `safe-outputs`
   (`upload-artifact`, `create-pull-request`, `push-to-pull-request-branch`,
   `add-labels`, `add-comment`, `noop`, depending on the workflow).

When both surfaces exist, they share the same rubric under
`.github/aw/shared/<name>-policy.md` so they cannot drift in policy. Keep
interactive-only references under `.agents/skills/<name>/references/` when the
gh aw workflow does not need to import them.

## Prerequisite

Install the `gh aw` extension once per workstation, pinned to the version this
repo's `.github/aw/actions-lock.json` is built against. The canonical pin and
install command live in [`.github/aw/README.md`](../../.github/aw/README.md) →
"Pinning".

## File layout

```
.agents/skills/<name>/
├── SKILL.md                   # required — frontmatter + body
├── agents/
│   └── openai.yaml            # optional — Codex UI metadata and policy
├── references/                # optional — on-demand context for the agent
│   ├── CLASSIFICATION.md
│   ├── DOMAINS.md
│   └── TOOLS.md
└── assets/                    # optional — worked examples, fixtures
    └── examples.md

.claude/skills -> ../.agents/skills  # symlink, do not edit separately

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

1. **Skill body.** Create `.agents/skills/<name>/SKILL.md` with at minimum
   `name` and `description` in the frontmatter (see the
   [Agent Skills spec](https://agentskills.io/specification)). Keep SKILL.md
   short; push detail into `references/`.

2. **References.** Move anything load-bearing but stable into
   `references/<TOPIC>.md`. The agent loads them on demand; they keep
   SKILL.md scannable.

   **If you build both an interactive surface and an unattended twin,**
   the shared policy must live under `.github/aw/shared/<name>-policy.md`,
   not inside `.agents/skills/<name>/references/`. gh aw's runtime-import
   security validation forbids importing files outside `.github/`. The
   interactive skill references the same file via its repo-root path; the
   gh aw policy fragment imports it via
   <code v-pre>{{#runtime-import .github/aw/shared/&lt;name&gt;-policy.md}}</code>. See how the
   `sw-triage` skill wires it up for the exact pattern.

3. **Decide on the unattended path.** If the skill should also run in CI:
   create `.github/workflows/<name>.md` (gh aw frontmatter) plus
   `.github/aw/<name>-policy.md` (frontmatter-free fragment, runtime-imported
   by the workflow). Make the interactive skill explicit-only by adding
   `disable-model-invocation: true` to `SKILL.md` for Claude Code and
   `policy.allow_implicit_invocation: false` to `agents/openai.yaml` for Codex,
   then run `gh aw compile`. The mechanics — secrets remap, engine model pin,
   registration trick, output validation — live in
   [`.github/aw/README.md`](../../.github/aw/README.md).

4. **Update the catalogue.** Add a row to `.agents/skills/README.md`
   describing the trigger phrases and the deliverable.

5. **Run it once.** `gh aw run <name> -f …` and inspect with
   `gh aw audit <run-id>`.

## Skill-specific conventions

- **CI twins are explicit-only in interactive sessions.** A skill with a
  GitHub Agentic Workflow twin must not auto-load from an ordinary conversation.
  Set `disable-model-invocation: true` in `SKILL.md` for Claude Code and
  `policy.allow_implicit_invocation: false` in `agents/openai.yaml` for Codex.
  Users can still invoke it deliberately as `/name` in Claude Code or `$name`
  in Codex.
- **Frontmatter `description` is matched against user messages** in the
  interactive surface unless automatic invocation is disabled. Be specific
  about trigger phrases for skills that may auto-load.
- **References load on demand.** Keep SKILL.md scannable; push lookups,
  taxonomies, and tool catalogues into `references/`.
- **Default to Sonnet; escalate with a reason.** gh aw workflows pin the
  concrete model version in their own `engine.model` frontmatter (the single
  source of truth) — the Sonnet tier by default (e.g. `sw-triage`, `sw-review`).
  Escalate only with a concrete reason, documented in the workflow source
  comment — currently `sw-bugfixer` (code-fixing runs) and the
  `security`/`architecture` personas of `sw-review` escalate to the Opus tier.
- **Inline sub-agents (`## agent:` blocks in a gh aw source) have three
  hard-won requirements** (see `sw-review.md` for the working pattern):
  - The frontmatter **must include `name:`** — Claude Code registers a
    sub-agent only via that field, not via the file name. Without it the
    orchestrator silently reviews inline and any per-agent `model:` pin
    (e.g. an Opus escalation) never applies.
  - **Restrict `tools:`** to what the worker needs (typically
    `Read, Grep, Glob, Bash`). Sub-agents otherwise inherit every tool,
    including safe-output MCP tools — a worker that publishes on its own can
    consume capped safe-output quotas meant for the orchestrator.
  - Workers **return their result as the final message**; only the
    orchestrator publishes. State this in both the worker prompt and the
    orchestrator prompt, and make dispatch mandatory ("you MUST dispatch via
    the `Task` tool; never review inline") — a soft "otherwise do it
    yourself" fallback reliably degrades into inline handling.

## Reference docs

- [`.github/aw/README.md`](../../.github/aw/README.md) — gh aw setup,
  pinning, secrets, registration, output validation.
- [`.agents/skills/README.md`](../../.agents/skills/README.md) — interactive
  skill catalogue.
- [`gh aw` Reference](https://github.github.com/gh-aw/) — upstream docs.
- [Agent Skills specification](https://agentskills.io/specification) — the
  SKILL.md frontmatter contract.
