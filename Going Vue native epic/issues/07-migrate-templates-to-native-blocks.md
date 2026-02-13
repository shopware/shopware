# Issue 07: Migrate Core Templates to Native Vue Blocks

**Phase:** 2 — Migration Wave | **Priority:** High | **Estimate:** ~1h/component (~964 templates)
**Labels:** `migration`, `templates`, `native-blocks`, `component-migration`

---

## Summary

Migrate all ~964 core admin `.html.twig` templates from `{% block %}` definitions to native Vue templates with `<sw-block>` components, preserving all block names 1:1.

---

## Acceptance Criteria

- [ ] Every `{% block name %}` → `<sw-block name="name">` with **identical name**
- [ ] Every `{% parent %}` → `<sw-block-parent />`
- [ ] No block names renamed, merged, or split
- [ ] Block nesting hierarchy preserved exactly
- [ ] Each migrated template passes extension integration tests (Issue #05)
- [ ] Block name registry CI check passes (Issue #06)
- [ ] Vue template compilation succeeds
- [ ] Visual regression: renders identically to Twig original

---

## Migration Workflow Per Component

1. **Run codemod**: `npm run codemod:twig-remove-blocks`
2. **Run ESLint rules**: `replace-top-level-blocks-to-extends`, `move-v-if-conditions-to-blocks`, `move-slots-to-wrap-blocks`, `remove-empty-templates`
3. **Manual review**: Edge cases codemod couldn't handle
4. **Rename file**: `.html.twig` → `.html`
5. **Run tests**: Extension integration suite
6. **Verify blocks**: `npm run generate-blocks-list` + compare baseline

### Batching Strategy

1. Leaf components (low risk) → 2. Shared components → 3. Module pages → 4. Complex components

| Module | Templates |
|--------|-----------|
| `sw-product` | ~80 |
| `sw-order` | ~60 |
| `sw-settings` | ~100 |
| `sw-cms` | ~70 |
| Shared components | ~150 |
| Other | ~504 |

---

## Risks

- **Codemod covers ~90%** — remaining ~10% (complex Twig logic, conditional blocks) needs manual work
- **File format decision**: `.html` vs SFC? Needs decision before migration starts
- **Parallel work**: Multiple devs can work on different modules, but need coordination for shared components
