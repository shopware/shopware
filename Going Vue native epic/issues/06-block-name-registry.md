# Issue 06: Block Name Registry

**Phase:** 1 — Foundation | **Priority:** High | **Estimate:** 1 week
**Labels:** `migration`, `infrastructure`, `tooling`, `developer-experience`

---

## Summary

Publish a machine-readable block name registry (`blocks-list.json`) as the authoritative source of truth for all admin block names. Used to validate 1:1 block name preservation during migration and detect accidental changes in CI.

---

## Acceptance Criteria

- [ ] `blocks-list.json` generated automatically from current templates
- [ ] Lists every block name with source file and component
- [ ] CI check compares current list against baseline — fails if blocks removed/renamed
- [ ] Additive changes (new blocks) allowed
- [ ] Versioned and published with each release
- [ ] Documented for plugin developer reference

---

## Technical Approach

**Existing tooling:** `scripts/generate-block-list/` + `npm run generate-blocks-list`

1. **Enhance generator**: Handle both `{% block %}` (Twig) and `<sw-block name="">` (native)
2. **Generate baseline**: Create 6.8.0.0 baseline
3. **CI comparison**: Regenerate and compare — fail if blocks missing, pass if only additions
4. **Allowlist**: For intentional removals (requires justification)
5. **Publish**: Include in npm package / documentation

### Registry Format

```json
{
  "version": "6.8.0.0",
  "blocks": [
    { "name": "sw_product_detail_content", "component": "sw-product-detail", "file": "...", "type": "twig|native" }
  ]
}
```
