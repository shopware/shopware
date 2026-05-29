---
name: triage
description: >
  Triage a Shopware 6 GitHub bug issue. Read the issue body, identify the affected
  code area via rg/git/gh, check for related fixes or duplicates, then emit a
  Markdown summary with disposition, severity, suggested domain labels, confidence,
  reasoning, and supporting evidence. Use when the user asks to triage, classify,
  label, or assess a Shopware issue, when they reference an issue by number
  (e.g. "#16599"), or when a new issue arrives that needs an initial dispositioning.
license: MIT
allowed-tools: Bash(rg:*) Bash(git log:*) Bash(git show:*) Bash(git diff:*) Bash(git blame:*) Bash(gh issue view:*) Bash(gh issue list:*) Bash(gh pr view:*) Bash(gh pr list:*) Bash(gh api repos/*/issues/*:*) Bash(gh api repos/*/pulls/*:*) Bash(find:*) Bash(ls:*) Read Glob Grep
---

# Shopware Issue Triage

## Context (interactive)

You operate inside the `shopware/shopware` monorepo with full read access to the codebase and to GitHub via shell tools. You **cannot** label, close, assign, or comment on the issue — your Markdown summary is the deliverable, the user decides what to do with it.

This skill drives the **interactive** triage path (Claude Code / opencode / Codex CLI in the repo). The **unattended CI path** runs in GitHub Agentic Workflows (`gh aw`) and uses a parallel policy fragment at `.github/aw/triage-policy.md` that emits JSON via `upload-artifact` instead of Markdown. Both modes load the same shared policy from **references/POLICY.md** (role, research workflow, anti-reward-hacking) so they cannot drift on the rubric.

## How you are invoked

The user typed something like "triage issue #16599". Start with **Step 0** — fetch the issue yourself: `gh issue view <N> --json number,title,body,labels,state`. `GH_REPO` is set in env (`shopware/shopware`); no `--repo` flag needed. Work from `title` + `body` directly.

## Policy

Apply the shared policy in **references/POLICY.md**: your role, the 6-step research workflow, and the anti-reward-hacking rules.

## Output format

Emit a human-readable Markdown summary as your single final message. **No JSON, no code fence.** Use this structure:

```
## Triage — Issue #<N>: <one-line headline of the bug>

| Field | Value |
|---|---|
| **Disposition** | `valid-bug` / `duplicate` / `needs-info` / `not-a-bug` / `feature-request` |
| **Severity** | low / medium / high / critical |
| **Confidence** | 0.XX |
| **Suggested labels** | `domain/...` |
| **Duplicate of** | #N (or "—" if none) |
| **Change size** | quick-fix / small / medium / large / unknown |

### Reasoning

2–5 sentences referencing concrete paths, commit SHAs, related issue/PR numbers.

### Evidence

- "verbatim span 1"
- "verbatim span 2"

### Related work

- Affected paths: `src/Core/...`, `src/Administration/...`
- Related PRs: #16632, #16061
- Recent commits in area: `4cfe2b182ba fix: ...`

### Missing template fields

- `expected_behaviour` (or "none" if all present)
```

## Equivalent JSON shape (informational)

The same disposition + evidence maps 1:1 onto the strict JSON shape emitted by the unattended CI path (`.github/workflows/triage.md`). You do not emit JSON — but the field semantics are identical. Field rules and worked examples are in **assets/examples.md**.

## Final instruction

Apply the policy in references/POLICY.md, then emit your Markdown summary using the structure shown above. No JSON, no code fence. The Markdown is your only output.
