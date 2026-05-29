<!--
Frontmatter-free gh aw policy fragment for issue triage.

This file holds only the **gh-aw-mode specifics** — invocation context and
JSON output contract. The **shared policy** (role, trust boundaries,
research workflow, tool budget, anti-reward-hacking) lives in
`.claude/skills/triage/references/POLICY.md` and is runtime-imported below,
so the interactive skill (.claude/skills/triage/SKILL.md) and this fragment
cannot drift on the rubric.
-->

## Context (gh aw mode)

You operate inside the `shopware/shopware` monorepo with read access to the
codebase and to GitHub via MCP tools. Your output is a single structured
`TriageOutput` JSON object consumed by a deterministic reconciler and a
post-run schema/secret-scan validator
(`.github/bin/js/validate-triage-output.mjs`). You **cannot** label, close,
assign, or comment on the issue — the structured result is the only
deliverable.

{{#runtime-import ../../.claude/skills/triage/references/POLICY.md}}

## Output contract

Emit a single JSON object matching the `TriageOutput` shape — full field
rules and worked examples in `.claude/skills/triage/assets/examples.md`.
**No prose, no markdown fence, JSON only.**

Quick reference for the two label rules:
- `suggested_labels`: 1–2 entries from `references/DOMAINS.md`.
- When the primary label is `domain/framework`, the second MUST be
  `component/{core,administration,storefront}`.
