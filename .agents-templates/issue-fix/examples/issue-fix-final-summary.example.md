# Final Fix Summary

## Root Cause

The recalculation path rebuilt the affected line item from promotion data without carrying over the previously resolved label. As a result, the storefront later rendered an empty label for the recalculated item.

## Chosen Fix

The fix preserves the resolved label when rebuilding the recalculated line item and adds a regression test in the same cart area. This was chosen because it keeps the existing cart processing structure intact and changes only the missing state transfer.

## Why This Resolves The Issue

The label is now preserved across recalculation, so the storefront and downstream cart consumers continue to receive the expected line item data after promotions are recomputed.

## Checks

- Checks run: relevant PHPUnit test, `composer ecs`
- Self review completed: yes
- Review findings fixed: yes

## Git State

- Branch: fix/preserve-line-item-label-on-recalculation
- Changes staged: yes
- Commit created: no

## Suggested Commit Message

`fix: preserve recalculated line item labels`

## Suggested PR Title

`fix: preserve recalculated line item labels`

## Suggested PR Description

Preserve resolved line item labels during promotion recalculation and keep labels stable after promotion recomputation. Fixes: #12345
