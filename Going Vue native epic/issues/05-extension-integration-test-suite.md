# Issue 05: Extension Integration Test Suite

**Phase:** 1 — Foundation | **Priority:** High | **Estimate:** 3 weeks
**Labels:** `migration`, `testing`, `infrastructure`, `quality-assurance`

---

## Summary

Create a comprehensive integration test suite validating all extension scenarios work correctly across the migration. Every component migration must pass these tests before it's considered done.

---

## Extension Scenarios

1. **Component.extend()** on a migrated component produces a working derived component
2. **Options API override on Composition API** (via shim) — `Component.override()` with `$super()` on migrated component
3. **Composition API override** — `overrideComponentSetup()` with `previousState`
4. **Twig template override on native blocks** (via adapter) — `{% block %}` on `<sw-block>` component
5. **Native block override** — `<sw-block extends>` on native-block component
6. **Multi-level override chain** — core → Plugin A → Plugin B with `$super`/`previousState`/`{% parent %}` resolving through chain

---

## Additional Scenarios

- Override without calling parent
- Override replacing / wrapping a method
- Override adding reactive data or modifying computed
- Override accessing injected services or mixin-provided methods
- Template override with data binding and event handlers
- Multiple overrides on same block from different plugins
- HMR survival for overrides

---

## Acceptance Criteria

- [ ] All 6 core scenarios covered
- [ ] Tests run in CI for every admin PR
- [ ] Tests validate both new system AND compatibility shims
- [ ] Realistic plugin pattern fixtures (not trivial)
- [ ] 50+ distinct test scenarios
- [ ] Documented so migration PRs reference which tests to verify

---

## Key Assertions Per Scenario

1. Component renders correctly with override
2. Override logic executes in correct order
3. `$super` / `previousState` / `{% parent %}` resolve correctly
4. Reactive data flows through override chain
5. No console errors or Vue warnings
