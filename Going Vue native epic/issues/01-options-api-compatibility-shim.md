# Options API → Composition API Override Shim

**Phase:** 1 — Foundation | **Priority:** Critical | **Estimate:** 2-3 weeks
**Labels:** `migration`, `infrastructure`, `breaking-change-prevention`

---

## User story

As a **plugin developer**, I would like my existing `Component.override()` calls with Options API patterns (`methods`, `computed`, `data`, `watch`, `inject`) to **continue working when a core component is migrated to Composition API**, so that I can **adopt the new extension system at my own pace without my plugin breaking on update**.

### Context

When a core component migrates from Options API to Composition API (using `createExtendableSetup()`), existing plugin overrides will stop working because:

- `this.$super('methodName')` relies on the Options API method chain which no longer exists in `setup()`
- `this.xxx` property access breaks because Composition API uses refs, not `this`-bound properties
- Options API config merging (`methods`, `computed`, `data`) has no equivalent for a `setup()` function

A compatibility shim must transparently intercept these calls and translate them to the Composition API equivalents at runtime.

**Location:** `async-component.factory.ts`, `composition-extension-system.ts`

**Technical approach:**

1. **Detect mismatch**: When `Component.override()` receives Options API config but target uses `createExtendableSetup()`, activate the shim
2. **Convert methods** → functions wrapping `previousState.methodName()`
3. **Convert computed** → `computed()` accessing `previousState.xxx.value`
4. **Convert data()** → merge as additional `ref()`s
5. **Convert watch** → Composition API `watch()` calls
6. **Proxy `this`** → map property access to reactive refs from `previousState`

**Risks:**

- **Mixin shimming**: Complex — mixins merge multiple method/data sources. May need partial support with documented limitations.
- **Reactive unwrapping**: Nested objects (`this.product.name` vs `previousState.product.value.name`) may have edge cases.
- **Private state**: Plugins accessing unexposed internals won't be covered.

---

## Acceptance criteria

- [ ] `Component.override('sw-example', { methods: { save() { this.$super('save'); } } })` works when `sw-example` uses Composition API
- [ ] `this.$super('methodName')` maps to `previousState.methodName()` internally
- [ ] `this.propertyName` resolves to the corresponding reactive ref from Composition API state
- [ ] Options API `computed`, `data()`, `watch`, and `inject` overrides are converted to Composition API equivalents
- [ ] Multi-level override chains work (core → Plugin A → Plugin B)
- [ ] Deprecation warning logged when shim activates, linking to migration docs
- [ ] Negligible performance overhead

---

## Definition of Done

- [ ] Fulfills all acceptance criteria defined during discovery.
- [ ] Integration/E2E testing in staging is done.
- [ ] All integration/E2E/unit tests passing; all critical or high-priority bugs are resolved.
- [ ] Fulfills compliance, performance, security, and cloud-readiness needs.
- [ ] Documentation — developer docs are written or updated, including information on how the feature or change is adopted and tested.
- [ ] If the change affects the Administration, it has been tested in current versions of Firefox, Chrome, and Edge.
