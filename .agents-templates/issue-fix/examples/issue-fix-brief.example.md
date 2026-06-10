# Issue Fix Brief

## Issue

- Number: 12345
- Title: Cart line item label is empty after promotion recalculation
- URL: https://github.com/shopware/shopware/issues/12345

## Goal

Fix the regression where recalculating promotions can leave a cart line item without a visible label in the storefront and downstream cart handling.

## Current Understanding

- The issue reports that the label disappears after promotion recalculation.
- The expected behavior is that the label remains stable across recalculation.
- The affected area is likely in cart processing or line item reconstruction during promotion handling.

## Constraints

- Follow existing repository patterns.
- Apply Shopware 6 best practices.
- Run only the relevant checks for touched files.
- Stage changes only. Do not commit.

## Planned Approach

1. Reproduce the recalculation path in the cart code.
2. Identify where the line item label is lost or overwritten.
3. Preserve the label through recalculation with the smallest safe change.
4. Add or update a test covering the regression.
5. Run relevant PHP checks and tests.
6. Perform self review and fix review findings.

## Open Questions

- None at this time.
