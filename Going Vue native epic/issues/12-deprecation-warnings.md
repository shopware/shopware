# Issue 12: Deprecation Warnings for Legacy Patterns

**Phase:** 2 — Migration Wave | **Priority:** High | **Estimate:** 1 week
**Labels:** `migration`, `deprecation`, `developer-experience`, `plugin-ecosystem`

---

## Summary

Add runtime console warnings (dev-only, deduplicated) when legacy extension patterns are used, linking to migration docs. Guides plugin developers to adopt new patterns before legacy removal.

---

## Warning Points

| Pattern | Detection Point | Message |
|---------|----------------|---------|
| Options API override on Composition API component | `async-component.factory.ts` (shim activation) | Use `overrideComponentSetup()` instead |
| `$super()` via shim | Compatibility shim (Issue #01) | Use `previousState.method()` instead |
| Twig template override via adapter | Runtime adapter (Issue #02) | Use `<sw-block extends>` instead |
| Mixin usage | `Component.register/override()` with `mixins` | Use composables instead |
| Options API inject in override | Compatibility shim | Use Composition API `inject()` instead |

---

## Acceptance Criteria

- [ ] Each pattern triggers a console warning with: component name, deprecated pattern, migration doc link, removal version
- [ ] Warnings only in development mode (`process.env.NODE_ENV !== 'production'`)
- [ ] Deduplicated (same warning shown once per component, not per render)
- [ ] Suppressible via configuration for test environments

---

## Warning Format

```
[Shopware Migration] Deprecated: {pattern}
  Component: {name} | Plugin: {plugin}
  Guide: https://developer.shopware.com/docs/guides/admin-migration/{slug}
  Removal: next major version (TBD)
```
