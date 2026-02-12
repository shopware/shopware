# Issue 01: Build Options API → Composition API Override Shim

**Phase:** 1 — Foundation
**Priority:** Critical
**Estimate:** 2–3 weeks
**Labels:** `migration`, `infrastructure`, `breaking-change-prevention`

---

## Summary

Build a compatibility shim that allows existing Options API `Component.override()` calls to continue working when the target component has been migrated to Composition API. This is the single most important piece of infrastructure to prevent breaking changes for plugin developers during the migration.

---

## Problem

When a core component is migrated from Options API to Composition API (using `createExtendableSetup()`), existing plugin overrides that use `Component.override()` with Options API configuration (`methods`, `computed`, `data`, `watch`) will stop working because:

1. **`this.$super('methodName')`** relies on `addSuperBehaviour()` in `async-component.factory.ts`, which injects proxy methods into `config.methods`. In Composition API, there is no `methods` object.
2. **Options API config merging** (`methods`, `computed`, `data`) has no equivalent when the target is a `setup()` function.
3. **`this.xxx` access** to reactive data and injected dependencies breaks because Composition API uses refs, not `this`-bound properties.

---

## Acceptance Criteria

- [ ] A plugin using `Component.override('sw-example', { methods: { save() { this.$super('save'); } } })` continues to work when `sw-example` has been migrated to Composition API
- [ ] `this.$super('methodName')` calls in Options API overrides are internally mapped to `previousState.methodName()` calls
- [ ] `this.propertyName` access in Options API overrides resolves to the corresponding reactive ref from the Composition API component's public state
- [ ] Options API `computed` overrides are converted to Composition API `computed()` refs internally
- [ ] Options API `data()` overrides merge correctly with Composition API reactive state
- [ ] Options API `watch` overrides are converted to Composition API `watch()` calls internally
- [ ] Options API `inject` dependencies (e.g., `this.repositoryFactory`, `this.acl`) are available in the override context
- [ ] Deprecation warning is logged to console when the shim is activated, with a link to migration docs
- [ ] Multi-level override chains work correctly (Plugin A overrides core, Plugin B overrides Plugin A)
- [ ] Performance overhead of the shim is negligible for typical override scenarios

---

## Technical Approach

### Location

Primary implementation in or alongside:
- `src/core/factory/async-component.factory.ts`
- `src/app/adapter/composition-extension-system.ts`

### Implementation Steps

1. **Detect override type mismatch**: When `Component.override()` receives an Options API config but the target component uses `createExtendableSetup()`, trigger the shim path instead of the normal Options API merge
2. **Convert `methods` to `overrideComponentSetup`**: Each method in the override config becomes a function that wraps `previousState.methodName()` for `$super` calls
3. **Convert `computed` properties**: Each computed property becomes a `computed()` call that accesses `previousState.xxx.value`
4. **Convert `data()` return values**: Merge into the component's reactive state as additional `ref()`s
5. **Convert `watch` entries**: Transform into Composition API `watch()` calls
6. **Proxy `this` context**: Create a proxy object that maps `this.xxx` property access to the correct reactive ref values from `previousState`
7. **Preserve `$super` semantics**: The proxy should intercept `this.$super('name')` calls and route them to the previous override's function

### Key File References

| File | Relevance |
|------|-----------|
| `src/core/factory/async-component.factory.ts` | Current `$super` chain implementation (`addSuperBehaviour`, `resolveSuperCallChain`) |
| `src/app/adapter/composition-extension-system.ts` | `createExtendableSetup()`, `overrideComponentSetup()` — target extension system |
| `src/app/plugin/virtual-call-stack.plugin.ts` | `$super` call tracking via `_virtualCallStack` |

---

## Testing Requirements

- [ ] Unit tests for each Options API pattern conversion (methods, computed, data, watch, inject)
- [ ] Integration test: Options API override on a Composition API component with `$super` chain
- [ ] Integration test: Multi-level override chain (core → Plugin A → Plugin B)
- [ ] Integration test: Override accessing `this.repositoryFactory` and other injected services
- [ ] Performance benchmark: Shim overhead vs. native override

---

## Risks & Open Questions

- **Mixin shimming**: Can mixin-injected methods be made available through the shim? Mixins are particularly complex because they merge multiple method/data sources. This may require partial support with documented limitations.
- **Reactive unwrapping edge cases**: Nested reactive objects (`this.product.name` vs `previousState.product.value.name`) may have edge cases with deep reactivity.
- **Private state access**: Plugins that access component internals not exposed through the public API will not be covered by the shim. Need to document these limitations clearly.
