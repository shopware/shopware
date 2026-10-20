---
title: Administration lint gate
date: 2026-10-16
area: administration
tags: [administration, eslint, code-quality, agents]
---

## Context

Agents write a growing share of the Administration code, and they copy whatever they find next to it.
Most of the rules that would stop this only lived in documents, and those rules did not hold.
`AGENTS.md` asked for TypeScript, yet half of the components added in the last year were JavaScript.
The rules that ESLint checked did hold.

Turning such rules on for the whole codebase at once is not possible.
The Administration has more than 15,000 existing violations.

## Decision

Every rule a tool can check becomes part of `npm run lint`.
Existing violations are recorded in ESLint's own baseline, `eslint-suppressions.json`.
New code has to follow every rule, and the baseline may only shrink.

A CI job compares the per-rule totals of the baseline with the base commit.
It fails when a total grows, or when the baseline holds entries of a deleted file.
A rule that is new to the baseline passes only when the same pull request changes `eslint.config.ts`, which has a code owner.
The baseline has no code owner, because shrinking it needs no review.

Rules listed in `rulesWithoutInlineDisable` can't be disabled with a comment, so the baseline is the only way around them.

The workflow is described in `src/Administration/Resources/app/administration/technical-docs/11-code-quality/01-lint-gate.md`.

## Consequences

- New code follows the rules from its first commit, without a cleanup of the existing code first.
- Moving or renaming a file resurfaces its baselined violations, because the baseline stores paths.
  The developer fixes them or moves the entries; the totals stay the same.
- Every change that fixes a violation also edits the baseline.
- Comparing totals allows trading: a fixed violation in one file can pay for a new one of the same rule in another.
  The trade is visible in the diff of the baseline.
- Any change to `eslint.config.ts` lets new rules into the baseline, so reviewers check the baseline diff of such a change.
- The Storefront's Administration modules share the configuration and have their own baseline.
