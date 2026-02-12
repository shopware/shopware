# Issue 10: Publish Plugin Logic Migration Codemod

**Phase:** 2 — Migration Wave
**Priority:** High
**Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `composition-api`, `plugin-ecosystem`

---

## Summary

Publish a codemod tool for plugin developers that transforms their `Component.override()` calls from Options API patterns to `overrideComponentSetup()` with Composition API patterns. This codemod complements the template codemod (Issue #09) and together they provide comprehensive automated migration for the plugin ecosystem.

---

## Problem

Every plugin that uses `Component.override()` with Options API configuration (`methods`, `computed`, `data`, `watch`) will need to update their overrides when the Options API compatibility shim is deprecated. Manual migration is complex because it involves understanding reactive programming patterns, ref unwrapping, and the `previousState` API.

---

## Acceptance Criteria

- [ ] Published as an npm package or standalone CLI tool
- [ ] Transforms `methods` overrides → functions calling `previousState.method()`
- [ ] Transforms `this.$super('method')` → `previousState.method()` calls
- [ ] Transforms `computed` overrides → `computed()` with `previousState` access
- [ ] Transforms `data()` return values → `ref()` declarations
- [ ] Transforms `watch` entries → `watch()` with reactive source access
- [ ] Transforms `inject` usage → `inject()` calls inside setup
- [ ] Transforms `this.property` access → `previousState.property` access (with `.value` where needed)
- [ ] Handles `Component.override()` → `overrideComponentSetup()` wrapper transformation
- [ ] Provides dry-run mode
- [ ] Produces a report of transformations that need manual review
- [ ] Documented with before/after examples

---

## Technical Approach

### Transformation Rules

| Options API Pattern | Composition API Equivalent |
|---------------------|---------------------------|
| `Component.override('name', { ... })` | `Component.overrideComponentSetup('name', (previousState) => { ... })` |
| `methods: { foo() { this.$super('foo'); } }` | `const foo = () => { previousState.foo(); }` |
| `methods: { bar() { return this.x + 1; } }` | `const bar = () => { return previousState.x.value + 1; }` |
| `computed: { baz() { return this.x + 1; } }` | `const baz = computed(() => previousState.x.value + 1)` |
| `data() { return { count: 0 }; }` | `const count = ref(0)` |
| `watch: { x(newVal, oldVal) { ... } }` | `watch(() => previousState.x.value, (newVal, oldVal) => { ... })` |
| `inject: ['repositoryFactory']` | `const repositoryFactory = inject('repositoryFactory')` |
| `this.createNotificationSuccess(...)` | `const { createNotificationSuccess } = useNotification()` |
| `mixins: [Mixin.getByName('notification')]` | `const { ... } = useNotification()` (manual selection of needed methods) |

### AST Transformation Approach

The codemod uses AST (Abstract Syntax Tree) transformation via tools like `jscodeshift` or `ts-morph`:

1. **Parse** the plugin's JavaScript/TypeScript files
2. **Find** `Component.override()` call expressions
3. **Extract** the Options API configuration object
4. **Transform** each property (`methods`, `computed`, `data`, `watch`, `inject`) to its Composition API equivalent
5. **Generate** the new `overrideComponentSetup()` call
6. **Add imports** for Vue Composition API functions (`ref`, `computed`, `watch`, `inject`)
7. **Flag** complex cases that require manual review (dynamic method names, spread patterns, complex mixin usage)

### Existing Reference

The `eslint-rules/deprecation-rules/no-vue-options-api.js` rule already performs similar transformations for core components. The plugin codemod can reuse much of this logic, adapted for the plugin override context.

### CLI Interface

```bash
# Install
npm install -g @shopware/admin-logic-codemod

# Run on plugin directory
shopware-admin-logic-codemod ./src/Resources/app/administration/

# Dry run
shopware-admin-logic-codemod ./src/Resources/app/administration/ --dry-run

# Generate report
shopware-admin-logic-codemod ./src/Resources/app/administration/ --report
```

---

## Edge Cases to Handle

- [ ] `this.$super()` with dynamic method names (e.g., `this.$super(methodName)`)
- [ ] Methods that call multiple `$super` methods
- [ ] Computed properties that access other computed properties via `this`
- [ ] Watchers with `immediate: true` and `deep: true` options
- [ ] Object spread in data (`data() { return { ...this.$super('data'), extra: 1 } }`)
- [ ] Arrow function vs. regular function `this` binding differences
- [ ] Plugin overrides that use `created()`, `mounted()` lifecycle hooks
- [ ] Overrides with `template` property (combined logic + template override)

---

## Testing Requirements

- [ ] Unit tests for each transformation rule
- [ ] Integration test: Full plugin override transformation end-to-end
- [ ] Integration test: Transformed code works with `overrideComponentSetup()` at runtime
- [ ] Edge case tests for all cases listed above
- [ ] Validated against at least 5 popular community plugins

---

## Definition of Done

- Codemod is published and installable
- Documentation with before/after examples for every supported pattern
- Codemod handles 80%+ of plugin overrides automatically
- Report mode identifies remaining manual migration tasks
- Validated against real-world plugin overrides
