---
name: shopware-change-scope
description: >
  Scope Shopware bug fixes, cleanups, and behavioral changes. Use when the user says
  "fix #12345", "fix this issue", "fix this bug", "resolve the GitHub issue", "address
  the review comments", "apply the reviewer's feedback", "should I clean up the rest of
  this file too?", "while I'm here", "boyscouting", "is this a workaround or the root
  cause?", "are other callers affected?", "same bug elsewhere?", "keep the diff small",
  or "don't refactor". Covers fixing a GitHub issue, applying PR review feedback,
  deciding how far a cleanup goes, checking siblings and callers of shared code, and
  stopping a focused fix from growing into an unrelated refactor. Not for PR text,
  release notes, or tests.
license: MIT
---

# Shopware Change Scope

Find the smallest root-cause fix.

## Bug Fixes

1. Treat issue suggestions as hypotheses.
2. Grep callers and sibling paths before editing shared behavior.
3. Fix the boundary where the bug actually lives.
4. Keep framework-level bugs in framework code and feature-specific bugs out of broad framework paths.

## Boyscouting

- Apply the same cleanup across the touched file when it is safe and obvious.
- Mention nearby broader cleanup opportunities instead of silently expanding the PR.
- When touching tests, add cheap missing coverage in the same command/domain surface.
- Do not turn a focused fix into an unrelated refactor.
