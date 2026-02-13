# Breaking Change Mitigation Strategy

**Context:** Going Vue Native epic — migrating over 900 components and templates while ~3,000+ plugins depend on the current extension system.

**Goal:** Minimize the plugin breakage at the moment of migration as low as possible. Plugins break only in rare edge cases when they choose not to migrate within the deprecation window.

---

## 1. Runtime Compatibility Layers

Keep old plugin code working transparently at runtime, even after core internals change.

### Options API Override Shim

When a core component migrates to Composition API, the shim intercepts `Component.override()` calls and translates Options API patterns on the fly:

| Plugin writes | Shim translates to |
|--------------|-------------------|
| `this.$super('save')` | `previousState.save()` |
| `this.product` | `previousState.product.value` |
| `computed: { x() {} }` | `computed(() => ...)` |
| `data() { return {} }` | `ref()` declarations |

**Boundary:** Plugins accessing truly private internals (undocumented `this._xxx` properties) are out of scope. Document this clearly.

### Twig → Native Block Adapter

When a core template migrates to `<sw-block>`, the adapter intercepts Twig overrides and transforms them into native block overrides at runtime:

| Plugin writes | Adapter translates to |
|--------------|----------------------|
| `{% block name %}` | `<sw-block extends="name">` |
| `{% parent %}` | `<sw-block-parent />` |

**Boundary:** Edge cases like `v-if`, `<slot>`, etc. can't be taken into account. These will only work within the old Twig files, but not with the native block system.

### Design Rules for Both Layers

- **Lazy activation**: Only load shim/adapter code when a legacy override is detected. Zero overhead for shops without legacy plugins.
- **Transparent to the plugin**: No code changes required in the plugin. Override just works.
- **Deprecation signal**: Log a dev-mode warning on activation with migration link.

---

## 2. Contract Preservation

Define what is "the contract" and enforce it mechanically.

### Block Names

Block names are the template extension contract. Rules:
- Never rename, merge, split, or remove a block during migration
- Every `{% block name %}` becomes `<sw-block name="name">` with the **exact same name**
- Enforced by CI check comparing against committed baseline

### Public API Surface

Property and method names are the logic extension contract. Rules:
- Every `data()` property keeps its name as a `ref()` or `reactive()`
- Every `computed` keeps its name as a `computed()`
- Every `methods` entry keeps its name and signature as a function
- Returned from `createExtendableSetup()` under `public` — this is the stable override interface

### Event Names and Props

- Component `$emit` event names must not change
- Prop names and types must not change
- Slot names must not change

### Enforcement

| Contract | Enforcement Mechanism |
|----------|----------------------|
| Block names | `blocks-list.json` baseline + CI diff check |
| Public API names | Extension integration test suite |
| Override chain behavior | Commercial plugin tests in CI |

---

## 3. All-at-Once SFC Migration

Each core component is converted in a single pass to a full SFC: native Vue blocks + Composition API extension system together. This is the only viable approach because SFC format requires both template and logic to live in one file. There is no intermediate state per component.

### Two Valid Core States

At any point during the migration, every core component is in one of two states:

| State | Template | Logic | Extension System |
|-------|----------|-------|-----------------|
| **Old** | Twig `.html.twig` + `{% block %}` | Options API + `Component.override()` / `$super()` | Twig block inheritance |
| **New** | SFC with `<sw-block>` / `<sw-block-parent />` | Composition API + `createExtendableSetup()` / `overrideComponentSetup()` | Native block context |

No component exists in a mixed state (e.g. Composition API logic with a Twig template). The migration is atomic per component.

### Plugin Compatibility via Shims

Even after a core component is fully migrated to the new state, existing plugin overrides continue to work transparently through the runtime compatibility layers:

| Plugin Override Type | Core Component State | Works? |
|---------------------|---------------------|--------|
| `Component.override()` with Options API | New (SFC) | Yes — Options API shim translates to `previousState` calls |
| Twig `{% block %}` template override | New (SFC) | Yes — Twig adapter translates to `<sw-block extends>` |
| `overrideComponentSetup()` | New (SFC) | Yes — native, no shim needed |
| `<sw-block extends>` override | New (SFC) | Yes — native, no shim needed |

Plugin developers can migrate their overrides at their own pace. The shims buy them time without blocking core migration progress.

### Deprecation Timeline

```
6.8.0.0  ─── Shims & adapters ship. New APIs available. Migration tooling published.
             Legacy patterns still fully supported.

6.8.x.x  ─── Dev-mode deprecation warnings active.
             Codemods and ESLint plugin available.

6.9.0.0  ─── Legacy patterns still work but warnings escalate.
             Compatibility layers remain.

future   ─── Removal of shims, adapters, and Twig.js.
             Only planned after ecosystem adoption assessed.
```

Minimum overlap: **1 major version** between "new system available" and "old system removed". Target: **2 major versions** given ecosystem size.

---

## 4. Automated Migration Tooling

Reduce the effort plugins need to invest, targeting 80%+ automated transformation.

| Tool | What it Does | Target |
|------|-------------|--------|
| Template codemod | `{% block %}` → `<sw-block>` in plugin files | 80%+ of template overrides |
| Logic codemod | `Component.override()` → `overrideComponentSetup()` | 80%+ of logic overrides |
| ESLint plugin | Continuous warnings + auto-fixes in IDE/CI | Ongoing guidance |
| Deprecation warnings | Runtime console hints with migration links | Discovery |

### Codemod Design Principles

- **Dry-run first**: Always show what will change before applying
- **Report unknowns**: Flag patterns that need manual review instead of silently producing broken code
- **Idempotent**: Running the codemod twice produces the same result
- **Preserve formatting**: Don't reformat untouched code

---

## 5. Testing & Validation

### Integration Test Gate

No component migration merges without passing all 6 extension scenarios:
1. `Component.extend()` on migrated component
2. Options API override via shim
3. Composition API override
4. Twig template override via adapter
5. Native block override
6. Multi-level override chain

### Commercial Plugin Validation

Backward compatibility is validated against a large commercial plugin already in the pipeline. Every component migration must pass this plugin's test suite in CI before merging.

### Plugin Developer Test Harness

Published utility so plugin developers can self-validate:

```bash
npx @shopware/admin-compat-test ./src/Resources/app/administration/
```

---

## Summary: Defense in Depth

| Layer | Mechanism | Prevents |
|-------|-----------|----------|
| **Runtime** | Compatibility shim + Twig adapter | Plugin code breaking immediately |
| **Contract** | Block name registry + CI check | Silent extension contract violations |
| **Timeline** | Deprecation window (2+ major versions) | Forced emergency migrations |
| **Tooling** | Codemods + ESLint plugin | High manual migration effort |
| **Testing** | Integration tests + commercial plugin validation | Undetected regressions |
| **Communication** | Docs + warnings + workshops | Developers not knowing what to do |

The core principle: **every layer can fail, but no single failure should break plugins**. Shims cover runtime, CI checks cover contracts, tests catch regressions, and the deprecation timeline gives developers space to migrate on their own schedule.
