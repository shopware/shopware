---
name: reproduce
description: >
  Reproduce a Shopware 6 GitHub bug issue in CI. Read the issue, derive a repro
  plan (cheapest faithful layer, minimal build, exact fixtures and assertion),
  reproduce on the reported version and on trunk, and emit evidence (the verbatim
  script plus Playwright/HTTP output). Use when the user asks to reproduce a bug,
  verify whether an issue still occurs, check if a defect is fixed on trunk, or
  references an issue by number (e.g. "#16638") in a reproduction context.
license: MIT
allowed-tools: Bash(rg:*) Bash(git log:*) Bash(git show:*) Bash(git diff:*) Bash(git blame:*) Bash(gh issue view:*) Bash(gh issue list:*) Bash(gh pr view:*) Bash(gh pr diff:*) Bash(gh pr list:*) Bash(gh api repos/*/issues/*:*) Bash(gh api repos/*/pulls/*:*) Bash(find:*) Bash(ls:*) Read Glob Grep
---

# Shopware Issue Reproduction

## Context

You operate inside the `shopware/shopware` monorepo with read access to the codebase
and to GitHub. This skill turns a reported bug into a **repro plan** and, in CI,
drives the deterministic jobs that execute it. You do **not** label, comment, or push —
the structured output is the deliverable.

This skill drives the **interactive** path (Claude Code / opencode / Codex CLI in the
repo). The **unattended CI path** is a hand-written multi-job workflow at
`.github/workflows/reproduce.yml` — a parallel reported‖trunk matrix that a single-job
agentic workflow can't express — which runs the Analyze phase via
`anthropics/claude-code-action` and the deterministic legs as plain jobs. Both surfaces
share this rubric and `references/SCHEMA.md` so they cannot drift.

## Phases

```
Analyze  ──▶  Reproduce (matrix: reported ‖ trunk, parallel)  ──▶  Report
```

The agent owns only the thin slices — **Analyze**, repro-script derivation, and
**Report**. Provisioning, building, and running (HTTP client or Playwright) are
deterministic CI jobs, not agent turns. Cost = turns × context: keep the agent off the
expensive paths.

1. **Analyze** — emit `analysis.json` (see `references/SCHEMA.md`). Pick the cheapest
   faithful `layer`, the minimal `build_profile`, and derive `fixtures` + `assertion`
   from the linked fix PR's regression test and the DAL schema. **Derive, don't discover.**
2. **Reproduce** — one leg per `target`. The leg's `executor` is chosen by `layer`:
   `direct` (instantiate the service), `http` (input → output, HAR evidence), or
   `playwright` (UI only; screenshot/video/trace). Build only the surface the bug lives on.
3. **Report** — merge legs into `repro-output.json`, apply the verdict map, render a
   self-contained GitHub comment that embeds each leg's **verbatim script** and trimmed
   reporter output, and links the (ephemeral) artifacts.

## Discipline

- **Match env to surface.** `direct` / `http` legs build neither storefront nor theme.
- **Reuse over rebuild.** Pin the exact reported version; collapse to one leg when
  reported == trunk or on manual rerun.
- **Fail fast.** `not_reproduced` after one bounded re-check; `blocked` when the env is
  dead after one rebuild. Never grind, never yield mid-build (one-shot provision, poll
  until READY).

## Reference files

- `references/ANALYZE.md` — the Analyze-phase runbook (inputs, needs_info protocol,
  economy budget, confidence rules, outputs). The CI prompt supplies only run parameters
  and defers here.
- `references/SCHEMA.md` — the three JSON contracts and the universal rules.
- `references/executors/{http,playwright,direct}.md` — the per-executor authoring
  contract. After choosing the `layer`, read ONLY the file for its executor.

## Output format

- Wrapper-fed / CI: schema-compatible JSON only (`analysis.json`, `result.json`, or
  `repro-output.json` depending on phase).
- Interactive: compact Markdown — verdict, the layer chosen, and the verbatim repro
  script. No JSON, no telemetry.
