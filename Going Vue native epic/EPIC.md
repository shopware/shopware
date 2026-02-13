# Epic: Going Vue Native

## Administration Migration to Composition API & Native Vue Blocks

**Status:** Planning | **Target:** 6.8.0.0 → 6.9.0.0 | **Scope:** `src/Administration/Resources/app/administration/`

---

## Summary

Migrate the Shopware 6 Administration from Options API + Twig templates to Composition API + native Vue blocks. Affects ~600-700 components, ~964 Twig templates, and all plugins using `Component.extend()`, `Component.override()`, or Twig block overrides.

Goal: **migrate the internals while preserving the interface** via compatibility shims, runtime adapters, codemods, and clear deprecation timelines.

---

## Motivation

- **Vue 3 alignment**: Composition API enables better TypeScript, tree-shaking, and composables
- **Remove Twig.js**: Eliminate bundle size and runtime overhead from secondary template engine
- **Better DX**: SFCs provide better IDE support, type inference, and debugging
- **Simpler extensions**: Replace `$super` chain and Twig block inheritance with standard Vue patterns

---

## Architecture

| Layer | Current | Target |
|-------|---------|--------|
| Logic | Options API + `Component.override()` / `$super()` | Composition API + `overrideComponentSetup()` / `previousState` |
| Templates | Twig.js `.html.twig` + `{% block %}` / `{% parent %}` | Native Vue + `<sw-block>` / `<sw-block-parent />` |
| Reuse | Mixins (`Mixin.getByName()`) | Composables |
| DI | Options API `inject` (`this.xxx`) | Composition API `inject()` |

---

## Migration Phases

### Phase 1: Foundation (Current → 6.8.0.0)

| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 1 | [Options API Override Shim](./issues/01-options-api-compatibility-shim.md) | Critical | 2-3w |
| 2 | [Twig → Native Block Adapter](./issues/02-twig-to-native-block-adapter.md) | High | 3-4w |
| 3 | [Stabilize Composition Extension System](./issues/03-stabilize-composition-extension-system.md) | Critical | 2w |
| 4 | [Stabilize sw-block Components](./issues/04-stabilize-sw-block-components.md) | Critical | 2w |
| 5 | [Extension Integration Tests](./issues/05-extension-integration-test-suite.md) | High | 3w |
| 6 | [Block Name Registry](./issues/06-block-name-registry.md) | High | 1w |

### Phase 2: Migration Wave (6.8.0.0 → 6.9.0.0)

| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 7 | [Migrate Templates to Native Blocks](./issues/07-migrate-templates-to-native-blocks.md) | High | ~1h/component |
| 8 | [Migrate Logic to Composition API](./issues/08-migrate-logic-to-composition-api.md) | High | ~2-4h/component |
| 9 | [Plugin Template Codemod](./issues/09-plugin-template-codemod.md) | High | 2w |
| 10 | [Plugin Logic Codemod](./issues/10-plugin-logic-codemod.md) | High | 2w |
| 11 | [ESLint Migration Plugin](./issues/11-eslint-extension-migration-plugin.md) | Medium | 2w |
| 12 | [Deprecation Warnings](./issues/12-deprecation-warnings.md) | High | 1w |

### Cross-Cutting

| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 13 | [Plugin Migration Guide](./issues/13-plugin-migration-guide.md) | Critical | Ongoing |
| 14 | [CI Block Name Check](./issues/14-ci-block-name-check.md) | High | 1w |
| 15 | [Backward Compatibility Testing](./issues/15-backward-compatibility-testing.md) | High | 2w |

---

## Effort Summary

| Category | Estimate |
|----------|----------|
| Infrastructure (shims, adapters, tooling) | ~12-15 weeks |
| Template migration | ~6 months (parallelizable) |
| Logic migration | ~6-12 months (parallelizable) |
| Plugin tooling & communication | Ongoing |
| **Total elapsed** | **~12-18 months** |

---

## Key Principles

1. **Preserve the interface** — Public extension surface (property names, block names, events) must remain stable during transition
2. **One migration at a time** — Don't force simultaneous template AND logic migrations
3. **Automate** — Codemods and ESLint fixers for every pattern, target 80%+ coverage
4. **Deprecation discipline** — Minimum 1 major version overlap between old and new systems
5. **Block names are sacred** — Never rename, merge, or remove existing block names

---

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Plugin developers don't migrate | High | High | Long deprecation window (2+ major versions) |
| Runtime adapter edge cases | Medium | Medium | Comprehensive test suite |
| Block name mismatches | Low | High | CI check (Issue #14) |
| Performance regression from dual systems | Medium | Low | Lazy-load Twig.js only when needed |
| Complex override chains break | Medium | High | Integration tests (Issue #05) |

---

## Dependencies

- Composition extension system (`createExtendableSetup`, `overrideComponentSetup`) — prototyped
- Native block components (`sw-block`, `sw-block-parent`) — implemented (experimental)
- Existing codemods (`twig-block-removal`, `js-vue3-feature-flag-removal`) — available
- Existing ESLint rules (`no-vue-options-api`, block-related) — available
