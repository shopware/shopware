<!--
Frontmatter-free gh aw policy fragment for issue triage.

This file holds only the **gh-aw-mode specifics** — invocation context and
JSON output contract. The **shared policy** (role, trust boundaries,
research workflow, tool budget, anti-reward-hacking) lives in
`.github/aw/shared/triage-policy.md` and is runtime-imported below, so the
interactive skill (.claude/skills/triage/SKILL.md) and this fragment cannot
drift on the rubric. (Shared policy must live under `.github/` — gh aw
forbids runtime-imports outside `.github/` for security reasons.)
-->

## Context (gh aw mode)

You operate inside the `shopware/shopware` monorepo with read access to the
codebase and to GitHub via MCP tools. Your output is a single structured
`TriageOutput` JSON object consumed by a deterministic reconciler and a
post-run schema/secret-scan validator
(`.github/bin/js/validate-triage-output.ts`). You **cannot** label, close,
assign, or comment on the issue — the structured result is the only
deliverable.

{{#runtime-import .github/aw/shared/triage-policy.md}}

## Output contract

Emit a single JSON object matching the `TriageOutput` shape exactly. **No
prose, no markdown fence, JSON only.** No extra fields beyond those listed
here — the post-run validator enforces the exact shape and will fail on
unknown keys, missing fields, or field-name typos.

```json
{
  "disposition": "valid-bug | duplicate | needs-info | not-a-bug | feature-request",
  "severity": "low | medium | high | critical",
  "suggested_labels": ["domain/...", "component/... (only with domain/framework)"],
  "confidence": 0.0,
  "reasoning": "2-5 sentences referencing concrete paths, commit SHAs, related issue/PR numbers. Max 2000 chars.",
  "evidence_quotes": ["[issue] or [shell] prefixed verbatim spans, max 500 chars each, max 5 entries"],
  "duplicate_of": null,
  "missing_template_fields": [],
  "affected_paths": [],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "quick-fix | small | medium | large | unknown"
}
```

Field rules:
- **All 13 fields are required.** Use `null` for `duplicate_of` when not a
  duplicate; empty arrays `[]` for the list fields when nothing applies.
- `suggested_labels`: 1–2 entries from `.claude/skills/triage/references/DOMAINS.md`.
  When the primary label is `domain/framework`, the second MUST be
  `component/{core,administration,storefront}`.
- `evidence_quotes`: prefix each entry `[issue]` (from issue body/comments)
  or `[shell]` (from shell/MCP output).
- `duplicate_of`: plain integer (e.g. `15800`), not `"15800"` or `"#15800"`.
- `related_issues`/`related_prs`: arrays of plain integers, same shape rule.
- Do **NOT** add fields like `issue_number`, `title`, `evidence`,
  `summary` — they are not in the schema and will fail validation.

Worked examples (for shape and tone, not normative content) are in
`.claude/skills/triage/assets/examples.md` — accessible if the gh aw
sandbox allows reading from `.claude/`, otherwise refer to the schema above.
