# Issue 08: Migrate Core Component Logic to Composition API

**Phase:** 2 — Migration Wave | **Priority:** High | **Estimate:** ~2-4h/component (~600 components)
**Labels:** `migration`, `composition-api`, `component-migration`

---

## Summary

Migrate all ~600 core admin components from Options API to Composition API using `createExtendableSetup()`, preserving the public API surface (property/method names) for plugin compatibility.

---

## Acceptance Criteria

- [ ] Every `data()` property → `ref()` with **same name**
- [ ] Every `computed` → `computed()` with **same name**
- [ ] Every `methods` entry → function with **same name and signature**
- [ ] Every `inject` remains available in public API
- [ ] Mixins replaced by composables
- [ ] `createExtendableSetup()` used for every component
- [ ] Extension integration tests pass (Issue #05)
- [ ] TypeScript types correct
- [ ] Identical render and behavior after migration

---

## Migration Pattern

**Before:** Options API with `data`, `computed`, `methods`, `inject`, `mixins`
**After:** `createExtendableSetup()` returning `{ public: { ... }, private: { ... } }`

### Mixin → Composable Mapping

| Mixin | Composable |
|-------|-----------|
| `notification` | `useNotification()` |
| `listing` | `useListing()` |
| `validation` | `useValidation()` |
| `form-field` | `useFormField()` |
| `placeholder` | `usePlaceholder()` |
| `salutation` | `useSalutation()` |
| `sw-inline-snippet` | `useInlineSnippet()` |
| `position` | `usePosition()` |
| `remove-api-error` | `useRemoveApiError()` |
| `cart-notification` | `useCartNotification()` |
| `discount-type` | `useDiscountType()` |
| `user-settings` | `useUserSettings()` |

**Existing ESLint rule:** `no-vue-options-api.js` detects and auto-fixes many patterns.

---

## Batching

| Module | Components |
|--------|-----------|
| `sw-product` | ~60 |
| `sw-order` | ~45 |
| `sw-settings` | ~80 |
| `sw-cms` | ~50 |
| Shared | ~100 |
| Other | ~265 |

**Order:** Composables first → Leaf components → Shared → Module pages → Complex components

---

## Risks

- **Public/private boundary**: Need guidelines for what goes in `public` vs `private`. Default: everything accessible via `this.xxx` → `public`.
- **Mixin extraction**: Some mixins access host-specific data. Composables may need parameters.
- **Component.extend() compat**: Derived components must still work with migrated base.
- **Parallel with template migration**: Can do both per component if shims (Issues #01, #02) are in place.
