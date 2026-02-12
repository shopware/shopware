# Epic: Going Vue Native

## Administration Migration to Composition API & Native Vue Blocks

**Status:** Planning
**Target Start:** 6.8.0.0
**Target Completion:** 6.9.0.0
**Scope:** `src/Administration/Resources/app/administration/`

---

## Summary

Migrate the entire Shopware 6 Administration from its current architecture (Options API + Twig-based templates) to a modern Vue-native architecture (Composition API + native Vue blocks). This migration touches **every component** (~600–700 components, ~964 Twig templates) and **every plugin** that uses `Component.extend()`, `Component.override()`, or Twig block overrides.

The primary goal is to **migrate the internals while preserving the interface** — ensuring a smooth, staged transition that minimizes breaking changes for the plugin ecosystem through compatibility shims, runtime adapters, automated codemods, and clear deprecation timelines.

---

## Motivation

- **Modern Vue ecosystem alignment**: Composition API is the standard approach for Vue 3 development, unlocking better TypeScript support, tree-shaking, and code reuse via composables
- **Eliminate Twig.js runtime dependency**: The Twig.js template factory adds bundle size and runtime overhead; native Vue blocks provide the same extensibility without a secondary template engine
- **Improved developer experience**: Composition API + SFCs provide better IDE support, type inference, and debugging
- **Simpler extension system**: The current `$super` chain, virtual call stack, and Twig block inheritance are complex; the new system is more aligned with standard Vue patterns

---

## Architecture Overview

### Current State

| Layer | Technology | Extension Mechanism |
|-------|-----------|-------------------|
| Logic | Options API (`data`, `computed`, `methods`, `watch`) | `Component.override()` + `$super()` chain |
| Templates | Twig.js templates (`.html.twig`) | `{% block %}` / `{% parent %}` overrides |
| Mixins | Options API mixins (`Mixin.getByName()`) | Merged into component via Options API |
| DI | Options API `inject` (`this.repositoryFactory`, etc.) | Accessible via `this.xxx` in overrides |

### Target State

| Layer | Technology | Extension Mechanism |
|-------|-----------|-------------------|
| Logic | Composition API (`setup()`, `ref`, `computed`) | `overrideComponentSetup()` + `previousState` |
| Templates | Native Vue templates with `<sw-block>` | `<sw-block extends="name">` / `<sw-block-parent />` |
| Composables | Composable functions (replacing mixins) | Direct import and invocation in `setup()` |
| DI | Composition API `inject()` | Called inside `setup()`, accessed as direct refs |

---

## Migration Phases

### Phase 1: Foundation (Current → 6.8.0.0)
> Build the infrastructure that allows both old and new systems to coexist.

| # | Issue | Priority | Est. Effort |
|---|-------|----------|-------------|
| 1 | [Options API → Composition API Override Shim](./issues/01-options-api-compatibility-shim.md) | Critical | 2–3 weeks |
| 2 | [Twig → Native Block Runtime Adapter](./issues/02-twig-to-native-block-adapter.md) | High | 3–4 weeks |
| 3 | [Stabilize Composition Extension System](./issues/03-stabilize-composition-extension-system.md) | Critical | 2 weeks |
| 4 | [Stabilize sw-block / sw-block-parent Components](./issues/04-stabilize-sw-block-components.md) | Critical | 2 weeks |
| 5 | [Integration Test Suite for Extension Scenarios](./issues/05-extension-integration-test-suite.md) | High | 3 weeks |
| 6 | [Publish Block Name Registry](./issues/06-block-name-registry.md) | High | 1 week |

### Phase 2: Migration Wave (6.8.0.0 → 6.9.0.0)
> Migrate core components and provide tooling for plugin developers.

| # | Issue | Priority | Est. Effort |
|---|-------|----------|-------------|
| 7 | [Migrate Core Component Templates to Native Vue Blocks](./issues/07-migrate-templates-to-native-blocks.md) | High | ~1h/component (~964 templates) |
| 8 | [Migrate Core Component Logic to Composition API](./issues/08-migrate-logic-to-composition-api.md) | High | ~2–4h/component (~600 components) |
| 9 | [Publish Plugin Template Migration Codemod](./issues/09-plugin-template-codemod.md) | High | 2 weeks |
| 10 | [Publish Plugin Logic Migration Codemod](./issues/10-plugin-logic-codemod.md) | High | 2 weeks |
| 11 | [ESLint Plugin for Extension Pattern Migration](./issues/11-eslint-extension-migration-plugin.md) | Medium | 2 weeks |
| 12 | [Add Deprecation Warnings for Legacy Patterns](./issues/12-deprecation-warnings.md) | High | 1 week |

### Cross-Cutting Concerns
> Tasks that span across all phases.

| # | Issue | Priority | Est. Effort |
|---|-------|----------|-------------|
| 13 | [Plugin Developer Migration Guide & Communication](./issues/13-plugin-migration-guide.md) | Critical | Ongoing |
| 14 | [CI Check for Block Name Preservation](./issues/14-ci-block-name-check.md) | High | 1 week |
| 15 | [Backward Compatibility Testing Strategy](./issues/15-backward-compatibility-testing.md) | High | 2 weeks |

---

## Effort Estimates Summary

| Category | Estimated Effort |
|----------|-----------------|
| Infrastructure (shims, adapters, tooling) | ~12–15 weeks |
| Component template migration | ~6 months (parallelizable) |
| Component logic migration | ~6–12 months (parallelizable) |
| Plugin developer tooling & communication | Ongoing |
| **Total elapsed timeline** | **~12–18 months** |

> **Note:** Deprecation and removal of legacy systems (Options API shim, Twig block adapter, Twig.js dependency) will be planned separately once the migration wave is complete and ecosystem adoption is assessed.

---

## Key Principles

1. **Migrate the internals, preserve the interface** — A component's internal implementation can change, but its public extension surface (property names, method signatures, block names, event names) must remain stable during the transition period.
2. **One migration at a time for plugin developers** — Do not force simultaneous template AND logic migrations. Provide overlap periods.
3. **Automated over manual** — Provide codemods and ESLint fixers for every migration pattern. Target 80%+ automated transformation coverage.
4. **Deprecation timeline discipline** — Minimum 1 major version (the `x` in `6.x.y.z`) of overlap between old and new systems. Clear, documented timelines.
5. **Block names are sacred** — Block names are the extension contract. Never rename, merge, or remove existing block names during migration.

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Plugin developers don't migrate in time | High | High | Long deprecation window (2+ major versions) |
| Runtime adapter has edge cases | Medium | Medium | Comprehensive test suite for adapter |
| Block name mismatches during migration | Low | High | Automated CI check comparing block names |
| Performance regression from dual systems | Medium | Low | Lazy-load Twig.js only when legacy overrides exist |
| Complex override chains break | Medium | High | Integration test suite for common plugin patterns |

---

## Dependencies

- Composition extension system (`createExtendableSetup`, `overrideComponentSetup`) — already prototyped
- Native block components (`sw-block`, `sw-block-parent`) — already implemented (experimental)
- Existing codemods (`twig-block-removal`, `js-vue3-feature-flag-removal`) — available for adaptation
- Existing ESLint rules (`no-vue-options-api`, block-related rules) — available for extension
