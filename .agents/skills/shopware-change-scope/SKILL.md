---
name: shopware-change-scope
description: Scope Shopware bug fixes, cleanups, and behavioral changes. Use when fixing an issue, applying review feedback, deciding whether to broaden a cleanup, or checking that the change addresses the root cause instead of one symptom.
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
