# Issue 14: CI Check for Block Name Preservation

**Phase:** Cross-Cutting | **Priority:** High | **Estimate:** 1 week
**Labels:** `migration`, `ci`, `quality-assurance`, `infrastructure`

---

## Summary

CI pipeline check that verifies block names are preserved 1:1 during template migration. Compares current inventory against committed baseline, fails on removals/renames without explicit approval.

---

## Acceptance Criteria

- [ ] CI regenerates block list and compares against `blocks-list.json` baseline
- [ ] **Fails** if any baseline block is missing (removed/renamed)
- [ ] **Passes** if only new blocks added
- [ ] Clear diff output showing exactly which blocks were removed/renamed
- [ ] Allowlist for intentional removals (requires justification)
- [ ] Runs on every PR modifying template files, < 30 seconds
- [ ] Documented baseline update process

---

## Technical Approach

```yaml
block-name-check:
  steps:
    - run: npm run generate-blocks-list -- --output=current-blocks.json
    - run: node scripts/ci/compare-block-lists.js blocks-list.json current-blocks.json
```

### Allowlist Format

```json
{
  "allowedRemovals": [
    { "name": "sw_old_block", "reason": "Deprecated in 6.8, removed in 6.9", "approvedBy": "@lead" }
  ]
}
```

**Depends on:** Issue #06 (Block Name Registry)

---

## Done When

- CI check active on main branch
- Comparison script committed and tested
- Allowlist mechanism documented
- Runs in < 30 seconds
