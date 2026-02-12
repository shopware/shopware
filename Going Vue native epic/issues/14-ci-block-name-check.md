# Issue 18: CI Check for Block Name Preservation

**Phase:** Cross-Cutting (spans all phases)
**Priority:** High
**Estimate:** 1 week
**Labels:** `migration`, `ci`, `quality-assurance`, `infrastructure`

---

## Summary

Implement a CI pipeline check that automatically verifies block names are preserved 1:1 during the template migration. This check compares the current block name inventory against a committed baseline and fails the build if any block names are removed or renamed without explicit approval.

---

## Problem

Block names are the extension contract between core and plugins. During the migration of ~964 templates with ~5,000+ block definitions, there is a significant risk of accidental block name changes through:

- Typos during manual migration steps
- Codemod edge cases that alter block names
- Refactoring that merges, splits, or renames blocks
- Copy-paste errors

A single block name change can silently break every plugin that overrides that block, and the breakage only manifests at runtime.

---

## Acceptance Criteria

- [ ] CI job regenerates the block list from the current codebase
- [ ] CI job compares the regenerated list against the committed `blocks-list.json` baseline
- [ ] CI job **fails** if any block name present in the baseline is missing from the current list (block removed/renamed)
- [ ] CI job **passes** if new block names are added (additive changes are non-breaking)
- [ ] CI job produces a clear diff showing exactly which blocks were removed/renamed
- [ ] An allowlist mechanism exists for intentional block removals (requires explicit entry + comment/justification)
- [ ] CI job runs on every PR that modifies template files
- [ ] CI job is fast (< 30 seconds)
- [ ] Documentation explains how to update the baseline when blocks are intentionally changed

---

## Technical Approach

### Pipeline Integration

```yaml
# .github/workflows/block-name-check.yml (or equivalent)
block-name-check:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
    - run: npm ci
    - run: npm run generate-blocks-list -- --output=current-blocks.json
    - run: node scripts/ci/compare-block-lists.js blocks-list.json current-blocks.json
```

### Comparison Script

```javascript
// scripts/ci/compare-block-lists.js
// 1. Load baseline blocks-list.json
// 2. Load freshly generated current-blocks.json
// 3. Find blocks in baseline that are missing from current
// 4. Check allowlist for intentional removals
// 5. If any non-allowlisted blocks are missing → exit 1 with detailed report
// 6. If only additions → exit 0
```

### Output on Failure

```
❌ Block name check FAILED

The following blocks were removed or renamed:
  - sw_product_detail_content (was in: sw-product-detail.html.twig)
  - sw_order_detail_header_title (was in: sw-order-detail.html.twig)

If these removals are intentional, add them to block-name-allowlist.json
with a justification comment.

This check prevents accidental breaking changes for plugin developers
who override these blocks.
```

### Allowlist Format

```json
{
  "allowedRemovals": [
    {
      "name": "sw_old_deprecated_block",
      "reason": "Block was empty and unused. Deprecated in 6.8.0.0, removed in 6.9.0.0.",
      "removedIn": "6.9.0.0",
      "approvedBy": "@lead-developer"
    }
  ]
}
```

### Existing Tooling

- `scripts/generate-block-list/` — Block list generator (npm: `generate-blocks-list`)
- Issue #06 — Block name registry (provides the baseline)

---

## Testing Requirements

- [ ] Unit test: Comparison script detects removed blocks
- [ ] Unit test: Comparison script allows added blocks
- [ ] Unit test: Allowlist mechanism bypasses specific removals
- [ ] Integration test: CI job fails on a PR that removes a block
- [ ] Integration test: CI job passes on a PR that only adds blocks
- [ ] Integration test: CI job passes on a PR with allowlisted removal

---

## Definition of Done

- CI check is active on the main branch
- Comparison script is committed and tested
- Allowlist mechanism is documented
- CI check runs in < 30 seconds
- Team is trained on how to handle failures and update the baseline
