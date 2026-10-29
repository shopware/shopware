# Feature Flags & Deprecations

## Purpose

The feature flag system in Shopware 6 administration serves two primary purposes:

1. **Feature Rollout Control**: Enable gradual rollout of new features, allowing for A/B testing, beta releases, and controlled feature activation
2. **Deprecation Lifecycle Management**: Manage the deprecation process by providing warnings and controlling the removal timeline of deprecated functionality

For normative rules, see [Administration feature flags and deprecations](../../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md).

## Feature Flag System

### Architecture

The feature flag system is built around the `Feature` class located in `src/core/feature.ts`. This static class provides a centralized registry for all feature flags and their activation states.

```typescript
// Core Feature class structure
export default class Feature {
    static flags: { [featureName: string]: boolean } = {};
    
    static init(flagConfig: { [featureName: string]: boolean }): void
    static getAll(): { [featureName: string]: boolean }
    static isActive(flagName: string): boolean
    static triggerDeprecationOrThrow(majorFlag: string, message: string): void
}
```

### Flag Sources

Feature flags are sourced from multiple locations:

1. **Backend Configuration**: Flags are passed from the backend via the global `_features_` object
2. **Build-time Configuration**: Flags can be set during the build process
3. **Runtime Evaluation**: Flags are evaluated at runtime through the `Feature.isActive()` method

### Initialization

Feature flags are initialized early in the application bootstrap process in `src/core/shopware.ts`:

```typescript
/** Initialize feature flags at the beginning */
if (window.hasOwnProperty('_features_')) {
    Feature.init(_features_);
}
```

The `_features_` object is a global window property that contains all feature flag configurations passed from the backend.

### Global Type Definition

Feature flags are properly typed in the global type definitions:

```typescript
interface Window {
    _features_: {
        [featureName: string]: boolean;
    };
}

const _features_: {
    [featureName: string]: boolean;
};
```

## Usage Patterns

### 1. Conditional Component Rendering

Feature flags can be used in Vue templates for conditional rendering:

```vue
<template>
    <div v-if="isFeatureActive('NEW_FEATURE')" class="new-feature">
        <!-- New feature content -->
    </div>
    <div v-else class="legacy-feature">
        <!-- Legacy content -->
    </div>
</template>

<script>
export default {
    methods: {
        isFeatureActive(flagName) {
            return Feature.isActive(flagName);
        }
    }
}
</script>
```

### 2. Enabling Alternative Services/Logic Branches

Feature flags can control service behavior and logic branches:

```typescript
// In service or component logic
if (Feature.isActive('USE_NEW_API')) {
    // Use new API implementation
    return this.newApiService.getData();
} else {
    // Use legacy API implementation
    return this.legacyApiService.getData();
}
```

### 3. Runtime Feature Detection

Access feature flags directly through the global object:

```typescript
// Direct access to feature flags
if (window._features_.V6_8_0_0) {
    // Execute version-specific logic
}
```

## Deprecation Management

### Deprecation Annotation Standard

Deprecated Administration APIs use the standard deprecation annotation format:

```typescript
/**
 * @deprecated tag:v6.8.0 - Will be removed, use NewComponent instead
 */
```

The annotation format includes:
- `@deprecated` tag
- `tag:vX.X.X` version when the deprecation was introduced
- Description of the deprecation and recommended replacement

### Deprecation Process

See [Administration feature flags and deprecations](../../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md) for the required process.

### ESLint Deprecation Rules

The codebase uses ESLint rules to enforce deprecation standards:

```typescript
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
```

This rule ensures proper handling of deprecated features and prevents inappropriate usage.

### Runtime Deprecation Guards

`Feature.triggerDeprecationOrThrow(majorFlag, message)` guards a deprecated API where it is consumed. While
`majorFlag` is inactive it warns in development builds, once per message. Once the flag is active it throws:

```typescript
Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod() is deprecated. Use newMethod() instead.');
```

Code that has to stay backward compatible until the major, in core or in an extension, branches with the
experimental `Feature.branch()` instead of an `if (Feature.isActive(...))`. `beforeMajor` runs while the flag
is inactive, with the deprecations of that major silenced, `sinceMajor` once it is active. The old path may
still call deprecated APIs without reporting them, while calls outside of a branch still report, like with
the PHP `Feature::silent()`:

```typescript
Shopware.Feature.branch('V6_8_0_0', {
    beforeMajor: () => this.initModuleLocales(),
    sinceMajor: () => registerModuleLocales(),
});
```

The silencing ends when `beforeMajor` returns, so it only covers synchronous code; a returned promise throws.
It cannot reach templates either: a deprecated prop or component a template uses is reported by Vue's render.

Components and props carry a `deprecated` option instead, which `src/app/plugin/deprecation.plugin.ts`
checks in a global `beforeCreate()` hook: a deprecated component on every creation, a deprecated prop
whenever the parent supplies it. The message names the path of components that use it. As the error is
thrown inside a Vue hook, Vue's error handling applies: a development build aborts the mount, a
production build logs the error and keeps rendering.

A `majorFlag` that is not registered, like the typo `V6_8_0` or the minor `V6_7_5_0`, can never become
active. It logs a console error instead of the warning. So does a `deprecated` option that is no version,
like `true`.

Jest silences the `[Deprecation]` warnings through `global.allowedErrors`, because the suite runs both
sides of a flag. A test that asserts a warning spies on `console.warn`.

Deprecated APIs that stay usable past the next major, like the deprecated `$tc` and the vue-i18n 8 argument
order, use the major of their removal, e.g. `V6_9_0_0`. Put the caller and the replacement into the message, so
every call site is reported once and stays actionable. See `src/core/helper/i18n-legacy-syntax.helper.ts`.

`sw-deprecation-rules/require-deprecation-guard` keeps every `@deprecated tag:vX.Y.Z` declaration on this
lifecycle. What the marker sits on decides what it requires:

| The marker sits on | Required |
|---|---|
| a function, method, `computed` accessor, getter or class constructor | `triggerDeprecationOrThrow()` with the tag's major as its **first** statement |
| a component, in the file header or on its `Component.register()` | the root `deprecated` option, with the tag's version |
| a prop | the prop's `deprecated` option, with the tag's version |
| a value, type, import, `data`, `watch`, `provide`, `inject`, store `state` or `getters`, or a statement inside a body | nothing |

A file header without a component covers every export of the file. `@private` and `_`-prefixed declarations
and markers like "Will be private" are skipped. A class core creates on its own, like an API service, may guard
each method instead of its constructor. A function core runs on its own, like a lifecycle hook, takes an
`eslint-disable-next-line sw-deprecation-rules/require-deprecation-guard -- <reason>`.

The rule also checks the other way round: every `triggerDeprecationOrThrow('<flag>', …)` call needs a
`@deprecated tag:` marker on a declaration or statement that encloses it, or in the file header. A guard of a
deprecated argument or usage pattern takes a `// @deprecated tag:vX.Y.Z - …` comment right above it.

Pair runtime warnings with an autofixable ESLint rule where the migration is mechanical, like
`sw-core-rules/no-tc-translation`, and enable it in the extension tooling (`extension-tooling/eslint.mjs`).

## Coding Guidelines

See [Administration feature flags and deprecations](../../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md).

## Migration Path When Flag Removed

See [Administration feature flags and deprecations](../../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md).

## Common Patterns

### Version-Based Flags

Version-based feature flags are commonly used for deprecation management:

```typescript
// Flag naming pattern for versions
V6_8_0_0  // Enables features/warnings for version 6.8.0.0
V6_7_0_0  // Features that were introduced in 6.7.0.0
```

### Component Replacement

Feature flags are often used when replacing components:

```typescript
/**
 * @deprecated tag:v6.8.0 - Will be removed, use mt-button instead
 */
export default {
    name: 'sw-button',
    // Legacy component implementation
};
```

### Service Evolution

Feature flags help evolve services while maintaining compatibility:

```typescript
// Service method with feature flag
getData() {
    if (Feature.isActive('NEW_DATA_SOURCE')) {
        return this.getDataFromNewSource();
    }
    return this.getDataFromLegacySource();
}
```

## Debugging and Development

### Accessing Feature Flags in DevTools

Feature flags can be inspected and modified in browser DevTools:

```javascript
// View all active feature flags
console.log(window._features_);

// Check specific flag
console.log(Feature.isActive('FEATURE_NAME'));

// View all flags via Feature class
console.log(Feature.getAll());
```

### Testing with Feature Flags

For testing rules, see [Administration feature flags and deprecations](../../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md). Feature flags can be initialized in tests:

```javascript
describe('Component with feature flag', () => {
    beforeEach(() => {
        Feature.init({ NEW_FEATURE: false });
    });

    it('should show legacy behavior when flag is off', () => {
        // Test legacy behavior
    });

    it('should show new behavior when flag is on', () => {
        Feature.init({ NEW_FEATURE: true });
        // Test new behavior
    });
});
```

This comprehensive system ensures smooth feature rollouts and manageable deprecation cycles while maintaining code quality and developer experience.
