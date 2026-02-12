# Issue 12: Add Deprecation Warnings for Legacy Patterns

**Phase:** 2 — Migration Wave
**Priority:** High
**Estimate:** 1 week
**Labels:** `migration`, `deprecation`, `developer-experience`, `plugin-ecosystem`

---

## Summary

Add runtime deprecation warnings to the browser console when legacy extension patterns are used (Options API overrides, Twig block overrides, `$super()` calls, mixin usage). These warnings should be informative, linking to migration documentation, and should guide plugin developers to adopt the new patterns before the legacy systems are removed.

---

## Problem

Plugin developers need clear, timely signals that the extension patterns they rely on are being deprecated. Without deprecation warnings, developers may not discover that their plugins need migration until the breaking change happens, leading to emergency fixes and negative ecosystem impact.

---

## Acceptance Criteria

- [ ] Console warning when `Component.override()` is called with Options API config targeting a Composition API component (shim is active)
- [ ] Console warning when `this.$super()` is called through the compatibility shim
- [ ] Console warning when a Twig template override is applied through the runtime adapter
- [ ] Console warning when `mixins: [Mixin.getByName()]` is used
- [ ] Console warning when Options API `inject` pattern is used in an override context
- [ ] Each warning includes:
  - The component name being overridden
  - The specific deprecated pattern detected
  - A link to the relevant migration documentation
  - The version when the pattern will be removed
- [ ] Warnings are only shown in development mode (not in production builds)
- [ ] Warnings are deduplicated (same warning shown only once per component, not on every render)
- [ ] Warnings can be suppressed via configuration for testing environments

---

## Technical Approach

### Warning Locations

| Pattern | Detection Point | Warning |
|---------|----------------|---------|
| Options API override on Composition API component | `async-component.factory.ts` — when shim is activated | "Component.override() with Options API config is deprecated. Use overrideComponentSetup() instead." |
| `$super()` via shim | Compatibility shim (Issue #01) | "this.$super() is deprecated. Use previousState.method() in overrideComponentSetup() instead." |
| Twig template override via adapter | Runtime adapter (Issue #02) | "Twig template overrides are deprecated. Use <sw-block extends> syntax instead." |
| Mixin usage | `Component.register/override()` — when `mixins` array is present | "Mixins are deprecated. Use composables instead. See migration guide." |
| Options API inject in override | Compatibility shim | "Options API inject in overrides is deprecated. Use Composition API inject() instead." |

### Warning Format

```javascript
console.warn(
  `[Shopware Migration] Deprecated: ${patternDescription}\n` +
  `  Component: ${componentName}\n` +
  `  Plugin: ${pluginName || 'unknown'}\n` +
  `  Migration guide: https://developer.shopware.com/docs/guides/admin-migration/${guideSlug}\n` +
  `  Will be removed in: next major version (TBD)`
);
```

### Deduplication

Use a `Set` to track which warnings have been shown:

```javascript
const shownWarnings = new Set();
function warnOnce(key, message) {
    if (shownWarnings.has(key)) return;
    shownWarnings.add(key);
    console.warn(message);
}
```

### Production Check

Warnings should be gated behind a development mode check:

```javascript
if (process.env.NODE_ENV !== 'production') {
    warnOnce(key, message);
}
```

---

## Testing Requirements

- [ ] Unit test: Each warning is triggered by the correct pattern
- [ ] Unit test: Warnings are deduplicated (same warning only shown once)
- [ ] Unit test: Warnings are not shown in production mode
- [ ] Unit test: Warning message contains all required information
- [ ] Integration test: Warning appears in browser console when a legacy plugin override is active

---

## Definition of Done

- All deprecation warnings implemented at the listed detection points
- Warnings include actionable migration guidance with documentation links
- Warnings are deduplicated and development-only
- Documentation pages exist for each migration path linked from the warnings
