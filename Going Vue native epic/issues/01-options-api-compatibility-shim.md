# Issue 01: Options API → Composition API Override Shim

**Phase:** 1 — Foundation | **Priority:** Critical | **Estimate:** 2-3 weeks
**Labels:** `migration`, `infrastructure`, `breaking-change-prevention`

---

## Summary

Build a compatibility shim so existing Options API `Component.override()` calls continue working when the target component has been migrated to Composition API. This is the most critical infrastructure piece to prevent plugin breakage.

---

## Acceptance Criteria

- [ ] `Component.override('sw-example', { methods: { save() { this.$super('save'); } } })` works when `sw-example` uses Composition API
- [ ] `this.$super('methodName')` maps to `previousState.methodName()` internally
- [ ] `this.propertyName` resolves to the corresponding reactive ref from Composition API state
- [ ] Options API `computed`, `data()`, `watch`, and `inject` overrides are converted to Composition API equivalents
- [ ] Multi-level override chains work (core → Plugin A → Plugin B)
- [ ] Deprecation warning logged when shim activates, linking to migration docs
- [ ] Negligible performance overhead

---

## Technical Approach

**Location:** `async-component.factory.ts`, `composition-extension-system.ts`

1. **Detect mismatch**: When `Component.override()` receives Options API config but target uses `createExtendableSetup()`, trigger shim
2. **Convert methods** → functions wrapping `previousState.methodName()`
3. **Convert computed** → `computed()` accessing `previousState.xxx.value`
4. **Convert data()** → merge as additional `ref()`s
5. **Convert watch** → Composition API `watch()` calls
6. **Proxy `this`** → map property access to reactive refs from `previousState`

---

## Risks

- **Mixin shimming**: Complex — mixins merge multiple method/data sources. May need partial support with documented limitations.
- **Reactive unwrapping**: Nested objects (`this.product.name` vs `previousState.product.value.name`) may have edge cases.
- **Private state**: Plugins accessing unexposed internals won't be covered.
