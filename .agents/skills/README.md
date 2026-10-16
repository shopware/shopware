# Shopware AI Skills

Portable AI capabilities packaged in the [Anthropic Agent Skills](https://agentskills.io) format. `.agents/skills` is the canonical location; `.claude/skills` is a symlink for Claude Code. Skills normally match their `description` against the task (best-effort, model-decided — not guaranteed); skills with unattended CI twins require explicit invocation. Mandatory steps live in `AGENTS.md` so they apply even when no skill triggers.

## Available skills

| Skill | Invocation examples | What it does |
|---|---|---|
| [`sw-bugfixer`](sw-bugfixer/SKILL.md) | Explicit: `/sw-bugfixer …` (Claude) or `$sw-bugfixer …` (Codex) | Diagnoses a Shopware issue or Bugfixer PR feedback, applies a focused fix when appropriate, validates narrowly, and reports the change or no-op decision. |
| [`sw-triage`](sw-triage/SKILL.md) | Explicit: `/sw-triage …` (Claude) or `$sw-triage …` (Codex) | Triages a Shopware 6 GitHub bug issue — identifies the affected code area, checks for related fixes or duplicates, and emits a Markdown summary (disposition, severity, suggested labels, confidence, evidence). |
| [`sw-review`](sw-review/SKILL.md) | Explicit: `/sw-review …` (Claude) or `$sw-review …` (Codex) | Reviews a Shopware 6 PR or local diff through calibrated persona lenses, dedupes findings, and emits Markdown or schema-valid JSON depending on invocation mode. |
| [`sw-reproduce`](sw-reproduce/SKILL.md) | Explicit: `/sw-reproduce …` (Claude) or `$sw-reproduce …` (Codex) | Reproduces a bug on an already-running local instance (no provisioning): authors a bundle via the shared `repro` CLI/playbook, runs it single-leg against the live installed version, and reports the outcome with screenshots/video/test case. |
| [`nightly-triage`](nightly-triage/SKILL.md) | "triage this nightly run", "identify all failing tests from <Actions run link>", "group the integration-major failures into issues" | Sweeps a failing multi-job PHPUnit CI run — extracts failing tests per shard, clusters them into root causes (with local Docker verification), and files per-domain issues plus a parent tracking issue, routing collateral failures to the root-cause owner. |
| [`shopware-knowledge-capture`](shopware-knowledge-capture/SKILL.md) | "save this for later", "preserve this knowledge", "where should this information live" | Routes durable Shopware knowledge to the right home without duplicating rules or adding mechanical stubs. |
| [`shopware-change-scope`](shopware-change-scope/SKILL.md) | "fix this bug", "apply review feedback", "should we clean this up too" | Keeps bug fixes and cleanups scoped to the root cause while catching safe nearby consistency work. |
| [`shopware-release-docs`](shopware-release-docs/SKILL.md) | "does this need release notes", "add upgrade docs", "public API changed" | Decides whether a change needs developer-facing release info, upgrade notes, or API schema docs. |
| [`shopware-pr-hygiene`](shopware-pr-hygiene/SKILL.md) | "create a PR", "update the PR", "address review feedback" | Applies Shopware PR template, title, and follow-up commit conventions. |
| [`shopware-php-code`](shopware-php-code/SKILL.md) | "edit PHP code", "add migration", "add API route", "deprecate this" | Applies Shopware PHP architecture, public surface, migration, API schema, and deprecation rules. |
| [`shopware-admin-js`](shopware-admin-js/SKILL.md) | "edit Administration", "Admin UI", "Vue component", "Jest spec" | Applies Shopware Administration JS/TS/Vue architecture, ACL, Jest, and linting conventions. |
| [`shopware-phpunit-tests`](shopware-phpunit-tests/SKILL.md) | "write PHPUnit tests", "add data provider", "feature flag test" | Applies Shopware PHPUnit structure, fixtures, feature-flag, DBAL, coverage, and data-provider rules. |

## How auto-loading works

When you start a session in this repo with an Agent-Skills-compatible runtime:

1. The runtime scans `.agents/skills/` for `SKILL.md` files, or `.claude/skills/` in Claude Code.
2. Each skill's `description` frontmatter is matched against your message.
3. If a skill matches, its body (plus on-demand `references/`) is injected into the agent's context.

Skills with unattended CI twins opt out of this matching and load only after an
explicit `/name` (Claude Code) or `$name` (Codex) invocation.

## Unattended twins

A skill can additionally run unattended in CI via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw): a workflow source at `.github/workflows/<name>.md` plus a `runtime-import`-ed policy fragment at `.github/aw/<name>-policy.md`. When both surfaces exist, the shared rubric lives in `.github/aw/shared/<name>-policy.md` and is loaded by both surfaces — they cannot drift on the policy.

Current twins: `sw-triage`, `sw-bugfixer`, and `sw-review` (see `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`).

`sw-reproduce` follows the same pattern with one twist: its **cross-cutting rubric** (role, trust
boundary, faithfulness) is the shared fragment `.github/aw/shared/reproduce-policy.md` — `runtime-import`ed
by the CI workflow (`.github/workflows/reproduce.md`) and referenced by the skill, so the two can't
drift — while the larger **step-by-step playbook + executor guides** stay on disk in
`.github/actions/reproduce/prompt/` and are read at runtime by both surfaces (kept out of the lock on
purpose).

For the gh aw setup, secrets, and registration mechanics, see [`.github/aw/README.md`](../../.github/aw/README.md).

## Adding a new skill

See the checklist in [`coding-guidelines/core/agent-skills.md`](../../coding-guidelines/core/agent-skills.md) — required frontmatter, references layout, optional gh aw twin, registration trick, and engine pin convention.
