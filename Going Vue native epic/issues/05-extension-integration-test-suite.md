# Issue 05: Integration Test Suite for Extension Scenarios

**Phase:** 1 — Foundation
**Priority:** High
**Estimate:** 3 weeks
**Labels:** `migration`, `testing`, `infrastructure`, `quality-assurance`

---

## Summary

Create a comprehensive integration test suite that validates all extension scenarios (override, extend, template override) work correctly across the migration. This test suite serves as a safety net for every component migration — before a component is considered migrated, it must pass all extension scenario tests.

---

## Problem

The migration changes two foundational systems simultaneously (Options API → Composition API, Twig blocks → native blocks). Plugin developers rely on specific extension behaviors:

- `Component.override()` with `$super()` chains
- `Component.extend()` for creating derived components
- Twig template block overrides with `{% parent %}`
- Native block overrides with `<sw-block extends>`
- Mixed scenarios (Options API override on Composition API component via shim)

Without a thorough test suite validating these scenarios, regressions can slip through that would break the plugin ecosystem.

---

## Acceptance Criteria

- [ ] Test suite covers all 6 extension scenarios defined in the analysis (see below)
- [ ] Tests run in CI for every PR that touches administration code
- [ ] Tests validate both the new system AND the compatibility shims
- [ ] Test fixtures represent realistic plugin patterns (not just trivial cases)
- [ ] Test suite is documented so that component migration PRs reference which tests to verify
- [ ] At least 50 distinct test scenarios covering combinations of extension types

---

## Extension Scenarios to Test

### Scenario 1: Component Extension (`Component.extend()`)
A `Component.extend()` call on a migrated component still produces a working derived component.

```javascript
Shopware.Component.extend('my-product-detail', 'sw-product-detail', {
    // additional methods/data
});
```

### Scenario 2: Options API Override on Composition API Component (via Shim)
A `Component.override()` with Options API config targets a component that has been migrated to Composition API.

```javascript
Shopware.Component.override('sw-product-detail', {
    methods: {
        saveProduct() {
            this.$super('saveProduct');
            // custom logic
        }
    }
});
```

### Scenario 3: Composition API Override
`overrideComponentSetup()` correctly overrides a Composition API component.

```javascript
Shopware.Component.overrideComponentSetup('sw-product-detail', (previousState) => {
    const saveProduct = () => {
        previousState.saveProduct();
        // custom logic
    };
    return { saveProduct };
});
```

### Scenario 4: Twig Template Override on Native Block Component (via Adapter)
A Twig block override targets a component whose template now uses `<sw-block>`.

```twig
{% block sw_product_detail_content %}
    {% parent %}
    <my-custom-card />
{% endblock %}
```

### Scenario 5: Native Block Override
An `<sw-block extends>` override correctly extends a native block.

```html
<sw-block extends="sw_product_detail_content">
    <sw-block-parent />
    <my-custom-card />
</sw-block>
```

### Scenario 6: Multi-Level Override Chain
Multiple overrides from different plugins stack correctly (core → Plugin A → Plugin B), with `$super` / `previousState` / `{% parent %}` resolving through the chain.

---

## Additional Scenarios

- [ ] Override that adds new template content without calling parent
- [ ] Override that completely replaces a method implementation
- [ ] Override that wraps a method (before/after logic around `$super`)
- [ ] Override that adds new reactive data properties
- [ ] Override that modifies computed property behavior
- [ ] Override accessing injected services (`repositoryFactory`, `acl`, etc.)
- [ ] Override using mixin-provided methods (via shim)
- [ ] Template override with data binding to component state
- [ ] Template override with event handlers
- [ ] Multiple overrides on the same block from different plugins
- [ ] Override on a component that uses both native blocks and Composition API
- [ ] Hot module replacement (HMR) — overrides survive HMR during development

---

## Technical Approach

### Test Structure

```
tests/
└── e2e/  or  tests/unit/
    └── extension-scenarios/
        ├── fixtures/
        │   ├── sample-options-api-component.ts
        │   ├── sample-composition-api-component.ts
        │   ├── sample-twig-template.html.twig
        │   ├── sample-native-template.html
        │   └── sample-plugin-overrides/
        ├── component-extend.spec.ts
        ├── options-api-override-on-composition.spec.ts
        ├── composition-api-override.spec.ts
        ├── twig-override-on-native-blocks.spec.ts
        ├── native-block-override.spec.ts
        ├── multi-level-override-chain.spec.ts
        └── edge-cases.spec.ts
```

### Key Assertions

For each scenario, tests should verify:
1. Component renders correctly with override applied
2. Override logic executes in the correct order
3. `$super` / `previousState` / `{% parent %}` resolve to the correct parent content
4. Reactive data flows correctly through the override chain
5. No console errors or Vue warnings during render

---

## Testing Requirements

This issue IS the testing strategy — the deliverable is the test suite itself.

---

## Definition of Done

- 50+ test scenarios covering all extension patterns
- Tests pass in CI
- Documentation describes how to add extension tests for newly migrated components
- Test fixtures are realistic and represent common plugin patterns
