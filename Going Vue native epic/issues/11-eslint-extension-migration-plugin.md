# Issue 11: ESLint Plugin for Extension Pattern Migration

**Phase:** 2 — Migration Wave
**Priority:** Medium
**Estimate:** 2 weeks
**Labels:** `migration`, `tooling`, `developer-experience`, `eslint`, `plugin-ecosystem`

---

## Summary

Publish an ESLint plugin for plugin developers that detects deprecated extension patterns (Options API overrides, Twig block syntax references) and provides auto-fixable suggestions to migrate to the new Composition API and native Vue block patterns. This complements the codemods (Issues #09, #10) by providing ongoing, IDE-integrated migration guidance.

---

## Problem

Codemods are a one-time transformation tool. Plugin developers also need continuous feedback during development — when they write new code using deprecated patterns or miss a migration, they should get immediate warnings in their IDE and CI pipeline. An ESLint plugin provides this continuous guidance.

---

## Acceptance Criteria

- [ ] Published as an npm package (`@shopware/eslint-plugin-admin-migration` or similar)
- [ ] Rule: `no-options-api-override` — flags `Component.override()` with Options API config, suggests `overrideComponentSetup()`
- [ ] Rule: `no-super-call` — flags `this.$super()` usage, suggests `previousState.method()` pattern
- [ ] Rule: `no-twig-block-syntax` — flags Twig block patterns in template files
- [ ] Rule: `no-mixin-usage` — flags `mixins: [Mixin.getByName('...')]`, suggests composable replacement
- [ ] Rule: `no-options-api-inject` — flags Options API `inject` in override context, suggests Composition API `inject()`
- [ ] Rules are auto-fixable where the transformation is safe
- [ ] Rules provide clear error messages with links to migration documentation
- [ ] Plugin is configurable (enable/disable individual rules, set severity)
- [ ] Compatible with existing Shopware ESLint configuration

---

## Technical Approach

### Rules Overview

| Rule | Detects | Auto-Fix | Severity |
|------|---------|----------|----------|
| `no-options-api-override` | `Component.override()` with Options API object | Yes (simple cases) | Warning |
| `no-super-call` | `this.$super('method')` | Yes | Warning |
| `no-twig-block-syntax` | `{% block %}`, `{% parent %}` in templates | Yes | Warning |
| `no-mixin-usage` | `mixins: [Mixin.getByName()]` | Partial (suggests replacement) | Warning |
| `no-options-api-inject` | `inject: ['service']` in override context | Yes | Warning |
| `prefer-composable` | Direct mixin method calls like `this.createNotificationSuccess()` | Partial | Info |

### Existing Rules to Leverage

The core codebase already has several relevant ESLint rules:

| Existing Rule | Reuse Potential |
|--------------|-----------------|
| `no-vue-options-api.js` | Core logic for detecting Options API patterns |
| `no-deprecated-component-usage.js` | Pattern for detecting deprecated API usage |
| `replace-top-level-blocks-to-extends.js` | Block-related detection logic |

The plugin-facing rules should be adapted from these core rules, packaged for external consumption.

### Plugin Structure

```
@shopware/eslint-plugin-admin-migration/
├── src/
│   ├── rules/
│   │   ├── no-options-api-override.ts
│   │   ├── no-super-call.ts
│   │   ├── no-twig-block-syntax.ts
│   │   ├── no-mixin-usage.ts
│   │   ├── no-options-api-inject.ts
│   │   └── prefer-composable.ts
│   ├── configs/
│   │   ├── recommended.ts      # All rules as warnings
│   │   └── strict.ts           # All rules as errors
│   └── index.ts
├── tests/
├── docs/
│   └── rules/
│       ├── no-options-api-override.md
│       ├── no-super-call.md
│       └── ...
├── package.json
└── README.md
```

---

## Testing Requirements

- [ ] Each rule has unit tests with valid and invalid code examples
- [ ] Auto-fix tests verify the fix produces correct output
- [ ] Integration test: Plugin works with Shopware's ESLint config
- [ ] Integration test: Run against a sample plugin with mixed old/new patterns
- [ ] False positive tests: Rules don't flag non-override code

---

## Definition of Done

- Published npm package installable by plugin developers
- All rules implemented with auto-fix where applicable
- Documentation for each rule with examples
- Recommended config for easy adoption
- Validated against at least 3 real-world plugins
