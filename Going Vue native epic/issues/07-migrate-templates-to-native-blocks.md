# Issue 07: Migrate Core Component Templates to Native Vue Blocks

**Phase:** 2 — Migration Wave
**Priority:** High
**Estimate:** ~1 hour per component (~964 templates)
**Labels:** `migration`, `templates`, `native-blocks`, `component-migration`

---

## Summary

Migrate all core administration component templates from Twig-based `.html.twig` files with `{% block %}` definitions to native Vue templates using `<sw-block>` components. This is the bulk of the template migration work and should be done component-by-component, ensuring block name preservation and passing the extension integration tests.

---

## Problem

The current administration uses ~964 `.html.twig` template files with ~5,000+ `{% block %}` definitions. These templates are processed by Twig.js at runtime to resolve block inheritance. This:

- Adds a runtime dependency on Twig.js (bundle size)
- Introduces a non-standard template compilation step
- Prevents standard Vue tooling (Volar, Vue DevTools) from fully understanding templates
- Creates a proprietary extension mechanism that diverges from Vue ecosystem patterns

---

## Acceptance Criteria

- [ ] Every `{% block name %}` is converted to `<sw-block name="name">` with the **identical block name**
- [ ] Every `{% parent %}` is converted to `<sw-block-parent />`
- [ ] No block names are renamed, merged, or split during migration
- [ ] Block nesting hierarchy is preserved exactly
- [ ] New blocks may be added, but existing blocks must not be removed
- [ ] Each migrated template passes the extension integration test suite (Issue #05)
- [ ] The block name registry (Issue #06) CI check passes after each migration PR
- [ ] Twig-specific syntax (`{% extends %}`, `{% include %}`, etc.) is properly handled or removed
- [ ] Vue template compilation succeeds without errors after migration
- [ ] Visual regression: migrated templates render identically to the Twig originals

---

## Technical Approach

### Existing Codemod

The `scripts/codemods/twig-block-removal/` codemod already handles the core transformation:

| Twig Pattern | Vue Block Equivalent |
|-------------|----------------------|
| `{% block name %}` | `<sw-block name="name">` |
| `{% endblock %}` | `</sw-block>` |
| `{% parent %}` | `<sw-block-parent />` |
| `{% extends 'parent.html.twig' %}` | (removed — extends is implicit) |

**Command:** `npm run codemod:twig-remove-blocks`

### Migration Workflow Per Component

1. **Run codemod**: Apply `twig-block-removal` codemod to the component's template
2. **Run ESLint rules**: Apply the block-related ESLint rules:
   - `replace-top-level-blocks-to-extends.js` — converts `name` → `extends` on top-level blocks
   - `move-v-if-conditions-to-blocks.js` — hoists `v-if` from children to `<sw-block>`
   - `move-slots-to-wrap-blocks.js` — restructures slot/block nesting
   - `remove-empty-templates.js` — cleans up empty `<template>` tags
3. **Manual review**: Check for edge cases the codemod couldn't handle (complex Twig logic, conditional blocks)
4. **Rename file**: Change `.html.twig` to `.html` (or embed in SFC if applicable)
5. **Run tests**: Execute the extension integration test suite to verify overrides still work
6. **Verify block registry**: Run `npm run generate-blocks-list` and compare against baseline

### Migration Batching Strategy

Migrate components in module-level batches to keep PRs reviewable:

1. **Start with leaf components** (no overrides, few blocks) — low risk, validates the process
2. **Then utility/shared components** — `sw-card`, `sw-button`, etc.
3. **Then module pages** — `sw-product-detail`, `sw-order-detail`, etc.
4. **Finally, complex components** — components with many blocks and known plugin overrides

### Key File References

| File | Relevance |
|------|-----------|
| `scripts/codemods/twig-block-removal/index.ts` | Primary codemod for transformation |
| `eslint-rules/core-rules/` | ESLint rules for post-codemod cleanup |
| `scripts/generate-block-list/` | Block name registry generator |

---

## Sub-Tasks (Suggested Batching)

This issue can be broken into sub-issues per module. Estimated module counts:

| Module | Approximate Template Count |
|--------|---------------------------|
| `sw-product` | ~80 templates |
| `sw-order` | ~60 templates |
| `sw-customer` | ~40 templates |
| `sw-settings` | ~100 templates |
| `sw-cms` | ~70 templates |
| `sw-media` | ~30 templates |
| Shared components (`sw-card`, `sw-data-grid`, etc.) | ~150 templates |
| Other modules | ~434 templates |

---

## Testing Requirements

- [ ] Each migrated component's template compiles without errors
- [ ] Each migrated component renders identically (visual comparison)
- [ ] Extension integration tests pass (Scenarios 4 and 5 from Issue #05)
- [ ] Block name registry check passes in CI
- [ ] No console errors or Vue warnings during render

---

## Risks & Open Questions

- **Codemod coverage**: The codemod handles ~90% of cases. The remaining ~10% (complex Twig logic within blocks, conditional blocks, Twig includes) require manual migration. Need to track these exceptions.
- **File format decision**: Should migrated templates be `.html` files or embedded in SFCs? This affects the build pipeline and tooling. Decision needed before migration starts.
- **Parallel work**: Multiple developers can work on different modules simultaneously, but need coordination to avoid merge conflicts in shared components.
