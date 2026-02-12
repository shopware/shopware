# Issue 06: Publish Block Name Registry

**Phase:** 1 — Foundation
**Priority:** High
**Estimate:** 1 week
**Labels:** `migration`, `infrastructure`, `tooling`, `developer-experience`

---

## Summary

Publish and maintain a machine-readable block name registry (`blocks-list.json`) that serves as the authoritative source of truth for all block names in the administration. This registry is used to validate that block names are preserved 1:1 during the template migration and to detect accidental block name changes in CI.

---

## Problem

Block names are the **extension contract** between core and plugins. Plugin developers reference blocks by name in their template overrides (e.g., `{% block sw_product_detail_content %}`). If a block name is renamed, removed, or restructured during migration, every plugin targeting that block breaks silently.

Currently, tooling exists (`scripts/generate-block-list/`) to generate a block list, but it needs to be:
- Run as part of the build/CI pipeline
- Used as a comparison baseline to detect name changes
- Published for plugin developers to reference

---

## Acceptance Criteria

- [ ] `blocks-list.json` is generated automatically from the current template files
- [ ] The registry lists every block name with its source file and component
- [ ] A CI check compares the current block list against the baseline and fails if any block is removed or renamed
- [ ] New blocks being added is allowed (additive changes are non-breaking)
- [ ] The registry is versioned and published with each release
- [ ] Documentation explains how plugin developers can use the registry to verify their overrides

---

## Technical Approach

### Existing Tooling

The `scripts/generate-block-list/` directory already contains a block list generator. The npm script `npm run generate-blocks-list` runs it.

### Implementation Steps

1. **Review and enhance the generator**: Ensure it handles both `{% block %}` (Twig) and `<sw-block name="">` (native) block definitions
2. **Generate baseline**: Run the generator on the current codebase to create the 6.8.0.0 baseline `blocks-list.json`
3. **Add CI comparison**: Create a CI job that regenerates the block list and compares it against the committed baseline. If any block names are missing from the new list, the CI job fails with a clear error message.
4. **Add allowlist for intentional removals**: If a block is intentionally removed or renamed (rare, should require ADR), it can be added to an allowlist to bypass the CI check.
5. **Publish registry**: Include `blocks-list.json` in the npm package and/or make it available via a documentation URL.

### Registry Format

```json
{
  "version": "6.8.0.0",
  "generatedAt": "2026-02-11T00:00:00Z",
  "blocks": [
    {
      "name": "sw_product_detail_content",
      "component": "sw-product-detail",
      "file": "src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig",
      "type": "twig"
    },
    {
      "name": "sw_product_detail_content",
      "component": "sw-product-detail",
      "file": "src/module/sw-product/page/sw-product-detail/sw-product-detail.html",
      "type": "native"
    }
  ]
}
```

### Key File References

| File | Relevance |
|------|-----------|
| `scripts/generate-block-list/` | Existing block list generator |
| npm script `generate-blocks-list` | Existing npm command |

---

## Testing Requirements

- [ ] Unit test: Generator correctly extracts Twig block names
- [ ] Unit test: Generator correctly extracts native `sw-block` names
- [ ] Unit test: CI comparison detects removed blocks
- [ ] Unit test: CI comparison allows new (added) blocks
- [ ] Unit test: Allowlist mechanism works for intentional removals

---

## Definition of Done

- `blocks-list.json` is committed as baseline
- CI check is active and blocks PRs that remove/rename blocks
- Generator handles both Twig and native block formats
- Documentation for plugin developers is written
