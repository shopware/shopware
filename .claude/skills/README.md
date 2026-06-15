# Shopware AI Skills

Portable AI capabilities packaged in the [Anthropic Agent Skills](https://agentskills.io)
format. Auto-loaded by Claude Code, opencode, Codex CLI, Cursor, Gemini CLI and other
Agent-Skills-compatible runtimes when their `description` matches the user's message.

## Available skills

| Skill | Trigger phrases (examples) | What it does |
|---|---|---|
| [`reproduce`](reproduce/SKILL.md) | "reproduce #16638", "does this bug still happen on trunk?", "is this fixed?" | Turns a Shopware 6 bug issue into a repro plan (cheapest faithful layer, minimal build, exact fixtures + assertion), reproduces it on the reported version and on trunk, and emits evidence (the verbatim script plus HTTP/Playwright/PHPUnit output). |

## How auto-loading works

When you start a session in this repo with Claude Code / opencode / Codex CLI:

1. The runtime scans `.claude/skills/` for `SKILL.md` files.
2. Each skill's `description` frontmatter is matched against your message.
3. If a skill matches, its body (plus on-demand `references/`) is injected into the agent's context.

No flags, no plugins — drop into a session and just describe what you want.

## Unattended CI twin

A skill can also run unattended in CI. The `reproduce` skill's CI surface is a
[GitHub Agentic Workflow](https://github.com/github/gh-aw) pair (same architecture as
`triage` / `bugfixer`): [`reproduce-analyze.md`](../../.github/workflows/reproduce-analyze.md)
runs the agentic Analyze phase and uploads the plan; [`reproduce-execute.yml`](../../.github/workflows/reproduce-execute.yml)
runs the deterministic reported‖trunk matrix + verdict + report on `workflow_run`. Both share
the skill's rubric and [`references/SCHEMA.md`](reproduce/references/SCHEMA.md) so the
interactive and unattended paths cannot drift. See the pipeline overview at
[`.github/actions/repro/README.md`](../../.github/actions/repro/README.md).
