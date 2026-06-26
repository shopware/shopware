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

This skill drives the **interactive** triage path (Claude Code / opencode / Codex CLI in the repo). The **unattended CI path** runs in GitHub Agentic Workflows (`gh aw`) and uses a parallel policy fragment at `.github/aw/triage-policy.md` that emits JSON via `upload-artifact` instead of Markdown. Both modes load the same shared policy from **`.github/aw/shared/triage-policy.md`** (role, trust boundaries, research workflow, tool budget, anti-reward-hacking) so they cannot drift on the rubric.

## Invocation

The user typed something like "triage issue #16599". Apply the shared policy in **`.github/aw/shared/triage-policy.md`** — start with its Step 0 (fetch the issue) and continue through Step 6 (emit).

## Output format

Emit a human-readable Markdown summary as your single final message. **No JSON, no code fence.** Structure:

- **H2 headline:** `## Triage — Issue #<N>: <one-line defect description>`
- **Decision table:** Disposition, Severity, Confidence, Suggested labels, Duplicate of, Change size
- **Reasoning** (2–5 sentences, opens with the one-sentence defect restatement from Step 1)
- **Evidence** (bullet list, each prefixed `[issue]` or `[shell]`)
- **Related work** (Affected paths, Related PRs, Recent commits in area)
- **Missing template fields** (or "none")

See `assets/examples.md` for the rendered JSON shape; the Markdown layout mirrors it field-for-field.

## Final instruction

Apply the policy in `.github/aw/shared/triage-policy.md`, then emit your Markdown summary using the structure above. The Markdown is your only output.
