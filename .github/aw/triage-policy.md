<!--
Frontmatter-free gh aw policy fragment for issue triage.

This file holds only the **gh-aw-mode specifics** — invocation context,
GitHub MCP tool usage, JSON output contract. The **shared policy** (role,
research workflow, anti-reward-hacking) lives in
`.claude/skills/triage/references/POLICY.md` and is runtime-imported below,
so the interactive skill (.claude/skills/triage/SKILL.md) and this fragment
cannot drift on the rubric.
-->

## Context (gh aw mode)

You operate inside the `shopware/shopware` monorepo with read access to the
codebase and to GitHub via the available tools. Fetch the issue with the
github tool (`get_issue`, `get_issue_comments`). Your output is a single
structured `TriageOutput` JSON object consumed by a deterministic reconciler.
You **cannot** label, close, assign, or comment on the issue — the structured
result is the only deliverable.

{{#runtime-import .claude/skills/triage/references/POLICY.md}}

## Output contract

Emit a single JSON object matching the `TriageOutput` shape (field rules and
examples in assets/examples.md):

```json
{
  "disposition": "valid-bug | duplicate | needs-info | not-a-bug | feature-request",
  "severity": "low | medium | high | critical",
  "suggested_labels": ["domain/...", "component/... (only with domain/framework)"],
  "confidence": 0.0,
  "reasoning": "2-5 sentences referencing concrete paths, commit SHAs, related issue/PR numbers.",
  "evidence_quotes": ["verbatim spans from the issue or your shell output (max 500 chars each)"],
  "duplicate_of": null,
  "missing_template_fields": [],
  "affected_paths": [],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "quick-fix | small | medium | large | unknown"
}
```

`suggested_labels`: 1–2 entries. When the primary label is `domain/framework`,
the second MUST be a `component/{core,administration,storefront}` label
(see references/DOMAINS.md).
