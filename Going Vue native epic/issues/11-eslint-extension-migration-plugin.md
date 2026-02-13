# Issue 11: ESLint Plugin for Extension Pattern Migration

**Phase:** 2 — Migration Wave | **Priority:** Medium | **Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `eslint`, `plugin-ecosystem`

---

## Summary

Publish an ESLint plugin that detects deprecated extension patterns and provides auto-fixable suggestions to migrate to Composition API / native Vue blocks. Complements codemods (Issues #09, #10) with continuous IDE-integrated guidance.

---

## Rules

| Rule | Detects | Auto-Fix | Severity |
|------|---------|----------|----------|
| `no-options-api-override` | `Component.override()` with Options API | Yes (simple) | Warning |
| `no-super-call` | `this.$super('method')` | Yes | Warning |
| `no-twig-block-syntax` | `{% block %}`, `{% parent %}` | Yes | Warning |
| `no-mixin-usage` | `mixins: [Mixin.getByName()]` | Partial | Warning |
| `no-options-api-inject` | `inject: ['service']` in overrides | Yes | Warning |
| `prefer-composable` | Direct mixin method calls | Partial | Info |

---

## Acceptance Criteria

- [ ] Published as npm package (`@shopware/eslint-plugin-admin-migration` or similar)
- [ ] All rules above implemented with auto-fix where safe
- [ ] Clear error messages with links to migration docs
- [ ] Configurable severity, compatible with Shopware ESLint config
- [ ] `recommended` and `strict` preset configs

**Leverages existing rules:** `no-vue-options-api.js`, `no-deprecated-component-usage.js`, `replace-top-level-blocks-to-extends.js`

---

## Done When

- Published npm package
- All rules implemented with auto-fix
- Per-rule documentation with examples
- Validated against 3+ real-world plugins
