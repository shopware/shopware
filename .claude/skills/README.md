# Shopware AI Skills

Portable AI capabilities packaged in the [Anthropic Agent Skills](https://agentskills.io) format. Auto-loaded by Claude Code, opencode, Codex CLI, Cursor, Gemini CLI and other Agent-Skills-compatible runtimes when their `description` matches the user's message.

## Available skills

| Skill | Trigger phrases (examples) | What it does |
|---|---|---|
| [`triage`](triage/SKILL.md) | "triage issue #X", "classify this bug", "is this a duplicate", "what severity is #N" | Triages a Shopware 6 GitHub bug issue — identifies the affected code area, checks for related fixes or duplicates, and emits a Markdown summary (disposition, severity, suggested labels, confidence, evidence). |
| [`sw-review`](sw-review/SKILL.md) | "review PR #X", "security review this branch", "review my staged changes" | Reviews a Shopware 6 PR or local diff through calibrated persona lenses, dedupes findings, and emits Markdown or schema-valid JSON depending on invocation mode. |
| [`shopware-knowledge-capture`](shopware-knowledge-capture/SKILL.md) | "save this for later", "preserve this knowledge", "where should this information live" | Routes durable Shopware knowledge to the right home without duplicating rules or adding mechanical stubs. |
| [`shopware-change-scope`](shopware-change-scope/SKILL.md) | "fix this bug", "apply review feedback", "should we clean this up too" | Keeps bug fixes and cleanups scoped to the root cause while catching safe nearby consistency work. |
| [`shopware-release-docs`](shopware-release-docs/SKILL.md) | "does this need release notes", "add upgrade docs", "public API changed" | Decides whether a change needs developer-facing release info, upgrade notes, or API schema docs. |
| [`shopware-pr-hygiene`](shopware-pr-hygiene/SKILL.md) | "create a PR", "update the PR", "address review feedback" | Applies Shopware PR template, title, and follow-up commit conventions. |
| [`shopware-php-code`](shopware-php-code/SKILL.md) | "edit PHP code", "add migration", "add API route", "deprecate this" | Applies Shopware PHP architecture, public surface, migration, API schema, and deprecation rules. |
| [`shopware-admin-js`](shopware-admin-js/SKILL.md) | "edit Administration", "Admin UI", "Vue component", "Jest spec" | Applies Shopware Administration JS/TS/Vue architecture, ACL, Jest, and linting conventions. |
| [`shopware-phpunit-tests`](shopware-phpunit-tests/SKILL.md) | "write PHPUnit tests", "add data provider", "feature flag test" | Applies Shopware PHPUnit structure, fixtures, feature-flag, DBAL, coverage, and data-provider rules. |

## How auto-loading works

When you start a session in this repo with Claude Code / opencode / Codex CLI:

1. The runtime scans `.claude/skills/` for `SKILL.md` files.
2. Each skill's `description` frontmatter is matched against your message.
3. If a skill matches, its body (plus on-demand `references/`) is injected into the agent's context.

No flags, no plugins — drop into a session and just describe what you want.

## Unattended twins

A skill can additionally run unattended in CI via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw): a workflow source at `.github/workflows/<name>.md` plus a `runtime-import`-ed policy fragment at `.github/aw/<name>-policy.md`. The shared rubric lives in `references/POLICY.md` and is loaded by both surfaces — they cannot drift on the policy.

Current twins: `triage` (see `.github/workflows/triage.md` + `.github/aw/triage-policy.md`).

For the gh aw setup, secrets, and registration mechanics, see [`.github/aw/README.md`](../../.github/aw/README.md).

## Adding a new skill

See the checklist in [`coding-guidelines/core/agent-skills.md`](../../coding-guidelines/core/agent-skills.md) — required frontmatter, references layout, optional gh aw twin, registration trick, and engine pin convention.
