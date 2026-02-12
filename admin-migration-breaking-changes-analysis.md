# Reducing Breaking Changes in Shopware 6 Administration Migration

## Analysis of: Options API → Composition API & Twig Blocks → Native Vue Blocks

**Date:** 2026-02-11
**Scope:** `src/Administration/Resources/app/administration/`
**Target audience:** Shopware core engineering, architecture decision-makers

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Architecture Baseline](#2-current-architecture-baseline)
3. [Migration 1: Options API → Composition API](#3-migration-1-options-api--composition-api)
4. [Migration 2: Twig Blocks → Native Vue Blocks](#4-migration-2-twig-blocks--native-vue-blocks)
5. [Cross-Cutting Concerns](#5-cross-cutting-concerns)
6. [Recommended Migration Strategy](#6-recommended-migration-strategy)
7. [Appendix: Existing Tooling Inventory](#7-appendix-existing-tooling-inventory)

---

## 1. Executive Summary

The Shopware 6 administration faces two interconnected but distinct migrations that impact the plugin extension system. Together, they touch **every component** in the administration (~600–700 components, ~964 Twig templates) and **every plugin** that uses `Component.extend()`, `Component.override()`, or Twig block overrides.

**Key finding:** The biggest source of breaking changes is not the migration itself but the *extension contract* — how plugins hook into core components. Both migrations fundamentally change how extensions work:

| Migration | What Breaks | Severity | Mitigation Feasibility |
|-----------|-------------|----------|----------------------|
| Options API → Composition API | `$super()`, Options API overrides, mixins, `inject` pattern | **High** | High — dual-mode support is already prototyped |
| Twig Blocks → Native Vue Blocks | Template override syntax, `{% block %}` / `{% parent %}` | **High** | Medium — requires adapter or codemod for plugins |

**Bottom line:** Breaking changes can be reduced to near-zero for a transition period by running both systems in parallel, but they cannot be eliminated entirely. The goal should be a **staged deprecation** with clear timelines, codemods for plugin developers, and runtime compatibility adapters.

---

## 2. Current Architecture Baseline

### 2.1 Component Scale

| Metric | Count |
|--------|-------|
| `Component.register()` calls | ~600+ |
| `Component.extend()` calls | ~50+ |
| `.html.twig` template files | ~964 |
| Twig `{% block %}` definitions | ~5,000+ (estimated) |
| Mixins in use | ~15 distinct mixins |
| Components using `inject` | Majority (~500+) |
| Components with `$super()` usage | Present in override chains |

### 2.2 Current Extension Contract (Public API for Plugins)

Plugins interact with the administration through these stable APIs:

```javascript
// 1. Component Registration
Shopware.Component.register('my-component', { /* Options API config */ });

// 2. Component Extension (creates new component from existing)
Shopware.Component.extend('my-component', 'sw-base-component', { /* config */ });

// 3. Component Override (replaces existing component behavior)
Shopware.Component.override('sw-product-detail', {
    methods: {
        saveProduct() {
            this.$super('saveProduct');  // calls parent method
            // custom logic
        }
    }
});

// 4. Template Override (Twig blocks)
// In plugin's .html.twig file:
// {% block sw_product_detail_content %}
//     {% parent %}
//     <my-custom-card />
// {% endblock %}
```

### 2.3 Internal Mechanisms

The extension system relies on three interconnected factories:

1. **`async-component.factory.ts`** — Registers, extends, overrides component configs; builds `$super` chain
2. **`template.factory.js`** — Uses Twig.js at runtime to resolve block inheritance across component/override templates
3. **`virtual-call-stack.plugin.ts`** — Tracks the `$super` call chain at runtime via `_virtualCallStack`

These three form the **extension runtime** — the core contract that plugin developers depend on.

---

## 3. Migration 1: Options API → Composition API

### 3.1 What Breaks

#### Breaking Change 1: `this.$super()` Does Not Exist in Composition API

**Impact:** Every plugin that uses `Component.override()` with method calls through `$super()`.

The `$super()` mechanism is implemented by `addSuperBehaviour()` in `async-component.factory.ts`. It injects proxy methods into `config.methods` that walk the override chain via `resolveSuperCallChain()`. This is fundamentally tied to the Options API's `methods` object.

In Composition API, there is no `methods` object. Functions are plain closures returned from `setup()`, with no runtime-inspectable override chain.

**Replacement:** `overrideComponentSetup()` receives `previousState` which contains the original functions — the override calls them directly:

```javascript
// Options API (breaks)
this.$super('saveProduct');

// Composition API (replacement)
Shopware.Component.overrideComponentSetup()('sw-product-detail', (previousState) => {
    const saveProduct = () => {
        previousState.saveProduct();  // equivalent of $super
        // custom logic
    };
    return { saveProduct };
});
```

#### Breaking Change 2: `Component.override()` with Options API Config Won't Work on Composition API Components

**Impact:** Every existing plugin override targeting a component that has been migrated to Composition API.

When a core component switches from Options API to Composition API, its override surface changes completely. An Options API override (with `methods`, `computed`, `data`, `watch`) cannot merge into a `setup()` function.

#### Breaking Change 3: Mixins Become Composables

**Impact:** Plugins using `mixins: [Mixin.getByName('...')]` on overridden components.

Current mixins (`notification`, `listing`, `validation`, `form-field`, etc.) will be replaced by composables. Plugin code that relies on mixin-injected methods (e.g., `this.createNotificationSuccess()`) needs to change access patterns.

#### Breaking Change 4: `inject` Pattern Changes

**Impact:** Plugins accessing `this.repositoryFactory`, `this.acl`, etc. through Options API `inject`.

In Composition API, `inject()` is called inside `setup()` and returns direct references, not `this`-bound properties. Plugin overrides that reference `this.repositoryFactory` in method overrides would break.

#### Breaking Change 5: Reactive Data Access Patterns Change

**Impact:** Plugins accessing `this.someData` on overridden components.

Options API: `this.product`, `this.isLoading` (direct property access)
Composition API: `previousState.product.value`, `previousState.isLoading.value` (Ref unwrapping)

### 3.2 Strategies to Minimize Breaking Changes

#### Strategy A: Dual-Mode Override System (Recommended — Already Prototyped)

**Concept:** Keep both `Component.override()` (Options API) and `overrideComponentSetup()` (Composition API) running simultaneously. A component can be migrated to Composition API internally while still accepting Options API overrides through an adapter layer.

**Implementation approach:**

1. When a component is migrated to Composition API with `createExtendableSetup()`, its public state is exposed as reactive refs
2. Build an **Options API compatibility shim** that:
   - Converts Options API override config (`methods`, `computed`, `data`) into Composition API override calls
   - Maps `this.$super('methodName')` to `previousState.methodName()` internally
   - Wraps `inject` dependencies so they're available as `this.xxx` in the override context
3. Log deprecation warnings when the shim is used, guiding plugin developers to migrate

**Effort:** Medium — the `createExtendableSetup` / `overrideComponentSetup` infrastructure already exists in `composition-extension-system.ts`. The missing piece is the Options API → Composition API shim.

**Breaking changes eliminated:**
- `$super()` continues to work (via shim) ✓
- `Component.override()` with Options API config continues to work ✓
- `this.xxx` access in overrides continues to work ✓

**Breaking changes remaining:**
- New Composition API public surface may differ from Options API surface (new properties, renamed properties)
- Mixin behavior may not be 100% shimable

#### Strategy B: Component-by-Component Migration with Public API Preservation

**Concept:** When migrating each component, ensure the `createExtendableSetup()` public API exactly matches the previous Options API surface (same property names, same method signatures).

**Implementation rules:**

1. Every `data()` property becomes a `ref()` with the same name in the public API
2. Every `computed` property becomes a `computed()` with the same name
3. Every `methods` entry becomes a function with the same name and signature
4. The `inject` dependencies remain available (either through public or private API)
5. Document the mapping: `this.product` → `previousState.product` (auto-unwrapped in templates)

**Effort:** Low per component, but requires discipline across ~600 components.

**Breaking changes eliminated:**
- Property/method names stay stable ✓
- Template bindings (e.g., `v-model="product.name"`) continue to work ✓

#### Strategy C: Gradual Opt-In with Feature Flags

**Concept:** Use feature flags (like the existing `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`) to let each component be toggled between Options API and Composition API implementations.

**Implementation:**

```javascript
Shopware.Component.register('sw-product-detail', {
    setup(props) {
        if (Shopware.Feature.isActive('COMPOSITION_sw_product_detail')) {
            return createExtendableSetup({ name: 'sw-product-detail', props }, compositionSetup);
        }
        // Fall through to Options API
    },
    data() { /* Options API fallback */ },
    // ...
});
```

**Effort:** High — maintaining two implementations per component is costly.

**Breaking changes eliminated:** All (during flag-off period), but only delays the problem.

#### Strategy D: Automated Codemod for Plugin Developers

**Concept:** Provide a codemod tool that transforms plugin `Component.override()` calls from Options API to `overrideComponentSetup()`.

**Transformations:**

| Options API Pattern | Composition API Equivalent |
|---------------------|---------------------------|
| `methods: { foo() { this.$super('foo'); } }` | `const foo = () => { previousState.foo(); }` |
| `computed: { bar() { return this.x + 1; } }` | `const bar = computed(() => previousState.x.value + 1)` |
| `data() { return { baz: 1 }; }` | `const baz = ref(1)` |
| `watch: { x(val) { ... } }` | `watch(() => previousState.x.value, (val) => { ... })` |

**Effort:** Medium — similar to the existing `no-vue-options-api.js` ESLint rule, which already performs many of these transformations.

### 3.3 Recommendation for Options API → Composition API

**Use a combination of Strategy A + B + D:**

1. **Strategy A (Dual-Mode):** Build the Options API compatibility shim so existing plugins don't break immediately. This buys time.
2. **Strategy B (API Preservation):** When migrating each component, keep the same public property names and signatures.
3. **Strategy D (Codemod):** Provide plugin developers with automated migration tooling.

**Deprecation timeline:**
- **Phase 1 (v6.8):** Composition API available, Options API fully supported, shim active
- **Phase 2 (v6.9):** Deprecation warnings on Options API overrides targeting Composition API components
- **Phase 3 (v7.0):** Remove Options API shim; Options API overrides no longer supported

---

## 4. Migration 2: Twig Blocks → Native Vue Blocks

### 4.1 What Breaks

#### Breaking Change 1: Template Override Syntax Changes Completely

**Impact:** Every plugin that overrides a Twig template block.

```twig
{# Current: Plugin template override #}
{% block sw_product_detail_content %}
    {% parent %}
    <my-custom-card />
{% endblock %}
```

Must become:

```html
<!-- New: Plugin template override -->
<sw-block extends="sw_product_detail_content">
    <sw-block-parent />
    <my-custom-card />
</sw-block>
```

This is a **1:1 syntactic transformation** — the semantics are equivalent. But every plugin template file must be rewritten.

#### Breaking Change 2: Template File Format Changes

**Impact:** All plugin `.html.twig` template override files.

Currently, plugin template overrides are `.html.twig` files processed by Twig.js at runtime. With native blocks, templates become standard Vue templates (`.html` or embedded in SFCs). The build pipeline and template registration mechanism change.

#### Breaking Change 3: Block Context and Data Scoping

**Impact:** Plugins that access component data in template overrides.

In Twig blocks, the component's `this` context is implicitly available. In `sw-block`, data is explicitly passed via the `:data="$dataScope"` prop. Plugin overrides receive data through the block's data binding.

#### Breaking Change 4: Conditional Rendering Interactions

**Impact:** Templates where `v-if`/`v-else`/`v-else-if` chains cross block boundaries.

The ADR acknowledges this: inserting `<sw-block>` components into a `v-if`/`v-else` chain breaks Vue's conditional rendering because `<sw-block>` is a real component, not a transparent wrapper. The ESLint rule `move-v-if-conditions-to-blocks.js` exists to address this by hoisting `v-if` to the block level.

#### Breaking Change 5: Slot Composition Changes

**Impact:** Templates where `<template v-slot>` is used inside blocks.

The ADR notes that `<sw-block>` between a parent component and a `<template v-slot>` breaks slot composition. The ESLint rule `move-slots-to-wrap-blocks.js` addresses this by restructuring the DOM.

### 4.2 Strategies to Minimize Breaking Changes

#### Strategy E: Runtime Twig-to-Block Compatibility Adapter (Recommended)

**Concept:** Keep the Twig.js template factory running alongside the native block system. When a core component is migrated to native blocks, the adapter translates any remaining Twig-based plugin overrides into `sw-block extends` calls at runtime.

**Implementation approach:**

1. During component build, check if there are Twig template overrides registered for the component
2. If found, parse the Twig override to extract block names and content
3. Dynamically create `sw-block extends="blockName"` components for each overridden block
4. Mount these alongside the component's native blocks

**Effort:** High — requires bridging two template systems at runtime.

**Breaking changes eliminated:**
- Plugin Twig template overrides continue to work ✓
- `{% parent %}` is mapped to `<sw-block-parent />` internally ✓

**Breaking changes remaining:**
- Complex Twig logic (conditionals, loops within blocks) may not translate cleanly
- Performance overhead of running both template systems

#### Strategy F: Preserve Block Names 1:1 (Strongly Recommended)

**Concept:** When migrating from `{% block sw_product_detail_content %}` to `<sw-block name="sw_product_detail_content">`, keep the exact same block names.

This is critical because block names are the **extension contract**. Plugin developers reference them by name. Changing block names (e.g., renaming or restructuring) is what causes the most breakage.

**Implementation rules:**

1. Every `{% block name %}` becomes `<sw-block name="name">` with the **identical name**
2. No block name renames, merges, or splits during migration
3. Block nesting hierarchy must be preserved
4. New blocks can be added, but existing ones must not be removed

**Effort:** Low — purely a naming discipline issue.

**Breaking changes eliminated:**
- Plugin overrides target the same block names ✓
- Block hierarchy expectations are preserved ✓

#### Strategy G: Codemod for Plugin Template Migration (Strongly Recommended)

**Concept:** Provide a codemod that transforms plugin `.html.twig` template overrides to native Vue block syntax.

The core team already has `scripts/codemods/twig-block-removal/index.ts` for internal use. A **plugin-facing version** should be published.

**Transformations:**

| Twig Pattern | Vue Block Equivalent |
|-------------|----------------------|
| `{% block name %}` | `<sw-block extends="name">` |
| `{% endblock %}` | `</sw-block>` |
| `{% parent %}` | `<sw-block-parent />` |
| `{% extends 'parent.html.twig' %}` | (removed — extends is implicit) |

**Effort:** Low — the codemod already exists for core templates. Adapt it for plugin context.

#### Strategy H: Hybrid Template Resolution (Transition Period)

**Concept:** Allow a component to have both Twig block definitions AND native blocks simultaneously during the transition period.

**Implementation:**

1. In `template.factory.js`, check if a component has native blocks registered
2. If yes, skip Twig block resolution for those specific blocks
3. If a Twig override targets a block that is now native, apply the compatibility adapter (Strategy E)
4. If a native block override exists, use it directly

**Effort:** Medium — requires coordination between template factory and block context.

### 4.3 Recommendation for Twig Blocks → Native Vue Blocks

**Use a combination of Strategy F + G + H:**

1. **Strategy F (Preserve Block Names):** Non-negotiable. Keep all block names identical.
2. **Strategy G (Plugin Codemod):** Publish the template migration codemod for plugin developers.
3. **Strategy H (Hybrid Resolution):** Allow both systems to coexist during the transition period.

If resources allow, add **Strategy E (Runtime Adapter)** for plugins that cannot migrate immediately.

**Deprecation timeline:**
- **Phase 1 (v6.8):** Native blocks available, Twig blocks fully supported, hybrid resolution active
- **Phase 2 (v6.9):** Deprecation warnings on Twig block usage, codemod published
- **Phase 3 (v7.0):** Remove Twig.js runtime; only native blocks supported

---

## 5. Cross-Cutting Concerns

### 5.1 Migration Order Matters

These two migrations are **coupled** because they both affect how plugins extend components. The recommended order:

1. **First: Twig Blocks → Native Vue Blocks** (template layer)
2. **Then: Options API → Composition API** (logic layer)

**Rationale:** Template migration is a more mechanical, syntactic transformation. Logic migration requires understanding each component's behavior. Doing templates first means:
- Components retain Options API during template migration → fewer moving parts
- Plugin developers face one migration at a time
- The native block system works with both Options API and Composition API components

Alternatively, they can be done **simultaneously per component** if:
- A component is fully migrated (both logic and template) in one PR
- The compatibility shims are in place for both systems
- Comprehensive tests validate plugin extension scenarios

### 5.2 Testing Strategy for Backward Compatibility

Every migrated component should have tests that validate:

1. **Extension test:** A `Component.extend()` call on the migrated component still works
2. **Override test (Options API):** A `Component.override()` with Options API config still works (via shim)
3. **Override test (Composition API):** `overrideComponentSetup()` works correctly
4. **Template override test (Twig):** A Twig block override still renders correctly (via adapter)
5. **Template override test (Native):** An `sw-block extends` override renders correctly
6. **$super chain test:** Multi-level override chains preserve call order

### 5.3 Plugin Developer Communication

Each migration phase should include:

1. **Migration guide** with before/after examples for every pattern
2. **Automated codemod** that handles 80%+ of transformations
3. **ESLint plugin** that flags deprecated patterns with fixable suggestions
4. **Deprecation warnings** in the browser console with links to migration docs
5. **Minimum 1 major version** of overlap between old and new system

### 5.4 Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Plugin developers don't migrate in time | High | High | Long deprecation window (2+ major versions) |
| Runtime adapter has edge cases | Medium | Medium | Comprehensive test suite for adapter |
| Block name mismatches during migration | Low | High | Automated CI check comparing block names |
| Performance regression from dual systems | Medium | Low | Lazy-load Twig.js only when legacy overrides exist |
| Complex override chains break | Medium | High | Integration test suite for common plugin patterns |

---

## 6. Recommended Migration Strategy

### Phase 1: Foundation (Current → v6.8 Stable)

| Action | Priority | Status |
|--------|----------|--------|
| Stabilize `createExtendableSetup()` and `overrideComponentSetup()` | Critical | ✅ Implemented (experimental) |
| Stabilize `sw-block` / `sw-block-parent` components | Critical | ✅ Implemented (no production usage yet) |
| Build Options API → Composition API override shim | Critical | ❌ Not started |
| Build Twig → Native Block runtime adapter | High | ❌ Not started |
| Publish block name registry (`blocks-list.json`) | High | ✅ Tooling exists |
| Create integration test suite for extension scenarios | High | Partial |

### Phase 2: Migration Wave (v6.8 → v6.9)

| Action | Priority |
|--------|----------|
| Migrate core components gradually (template + logic) | High |
| Preserve all block names 1:1 | Critical |
| Preserve all public API property/method names | Critical |
| Log deprecation warnings for legacy patterns | High |
| Publish plugin migration codemod | High |
| Publish ESLint plugin for extension pattern migration | Medium |

### Phase 3: Deprecation (v6.9 → v7.0)

| Action | Priority |
|--------|----------|
| Mark Options API override support as deprecated | High |
| Mark Twig block system as deprecated | High |
| Remove runtime adapters/shims | Medium |
| Remove Twig.js dependency | Medium |
| Finalize Composition API as sole extension mechanism | High |

### Migration Effort Estimates

| Task | Estimated Effort | Components Affected |
|------|-----------------|-------------------|
| Build Options API compatibility shim | 2–3 weeks | Infrastructure (1 file) |
| Build Twig→Block runtime adapter | 3–4 weeks | Infrastructure (2–3 files) |
| Migrate component templates to native blocks | ~1 hour/component | ~964 templates |
| Migrate component logic to Composition API | ~2–4 hours/component | ~600 components |
| Build plugin migration codemod | 2 weeks | 1 tool |
| Build integration test suite | 3 weeks | ~50 test scenarios |
| **Total infrastructure work** | **~12–15 weeks** | |
| **Total component migration** | **~6–12 months** (parallel) | |

---

## 7. Appendix: Existing Tooling Inventory

### Available Codemods

| Tool | Location | Purpose |
|------|----------|---------|
| Twig Block Removal | `scripts/codemods/twig-block-removal/` | Converts `{% block %}` → `<sw-block>` in core templates |
| JS Vue3 Feature Flag Removal | `scripts/codemods/js-vue3-feature-flag-removal/` | Removes Vue 2/3 feature flags from JS/TS |
| Twig Feature Flag Removal | `scripts/codemods/twig-feature-flag-removal/` | Removes Vue 2/3 feature flags from Twig |
| Block List Generator | `scripts/generate-block-list/` | Generates `blocks-list.json` from templates |

### Available ESLint Rules

| Rule | File | Purpose |
|------|------|---------|
| Replace Top-Level Blocks to Extends | `eslint-rules/core-rules/replace-top-level-blocks-to-extends.js` | Converts `name` → `extends` on top-level blocks |
| Move V-If to Blocks | `eslint-rules/core-rules/move-v-if-conditions-to-blocks.js` | Hoists `v-if` from children to `<sw-block>` |
| Move Slots to Wrap Blocks | `eslint-rules/core-rules/move-slots-to-wrap-blocks.js` | Restructures slot/block nesting |
| Remove Empty Templates | `eslint-rules/core-rules/remove-empty-templates.js` | Cleans up empty `<template>` tags |
| No Vue Options API | `eslint-rules/deprecation-rules/no-vue-options-api.js` | Detects and fixes Options API usage |
| No Deprecated Components | `eslint-rules/deprecation-rules/no-deprecated-component-usage.js` | Flags deprecated components |

### Key Implementation Files

| File | Purpose |
|------|---------|
| `src/core/factory/async-component.factory.ts` | Component registry, build, override, `$super` chain |
| `src/core/factory/template.factory.js` | Twig.js template resolution, block inheritance |
| `src/app/adapter/composition-extension-system.ts` | `createExtendableSetup()`, `overrideComponentSetup()` |
| `src/app/composables/use-block-context.ts` | Native block context (add/remove/get blocks) |
| `src/app/component/structure/sw-block-override/` | `sw-block` and `sw-block-parent` components |
| `src/app/plugin/virtual-call-stack.plugin.ts` | `$super` call tracking |

### npm Scripts

| Script | Command |
|--------|---------|
| Remove Twig blocks | `npm run codemod:twig-remove-blocks` |
| Remove JS feature flags | `npm run codemod:js-vue3-feature-flag-removal` |
| Remove Twig feature flags | `npm run codemod:twig-feature-flag-removal` |
| Generate block list | `npm run generate-blocks-list` |

---

## Summary of Approach to Minimize Breaking Changes

### Absolute Minimum Breaking Changes (Cannot Be Avoided)

1. **Eventual syntax change** for plugin template overrides (Twig → Vue blocks) — mitigated by codemod
2. **Eventual API change** for plugin logic overrides (`$super` → `previousState.method()`) — mitigated by codemod
3. **Mixin removal** — replaced by composables with different access patterns

### What CAN Be Preserved (Zero Breaking Change During Transition)

1. **Block names** — keep identical 1:1
2. **Public property/method names** — keep identical on public API surface
3. **`Component.register/extend/override` API** — keep working via shimming
4. **Twig template overrides** — keep working via runtime adapter during transition period
5. **`$super()` calls** — keep working via Options API compatibility shim during transition

### The Golden Rule

> **Migrate the internals, preserve the interface.** A component's internal implementation can change from Options API to Composition API, but its *public extension surface* (property names, method signatures, block names, event names) must remain stable across the transition period.
