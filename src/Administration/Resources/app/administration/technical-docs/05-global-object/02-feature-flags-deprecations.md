# Feature Flags & Deprecations

## Purpose

The feature flag system in Shopware 6 administration serves two primary purposes:

1. **Feature Rollout Control**: Enable gradual rollout of new features, allowing for A/B testing, beta releases, and controlled feature activation
2. **Deprecation Lifecycle Management**: Manage the deprecation process by providing warnings and controlling the removal timeline of deprecated functionality

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

All deprecated code must be marked with the standard deprecation annotation format:

```typescript
/**
 * @deprecated tag:v6.8.0 - Will be removed, use NewComponent instead
 */
```

The annotation format includes:
- `@deprecated` tag
- `tag:vX.X.X` version when the deprecation was introduced
- Description of the deprecation and recommended replacement

### Deprecation Process Steps

1. **Mark as Deprecated**: Add `@deprecated` annotation with target removal version
2. **Add Deprecation Warning**: Implement runtime warnings when deprecated functionality is used
3. **Update Documentation**: Document the migration path and replacement
4. **Feature Flag Integration**: Use feature flags to control deprecation warnings
5. **Removal**: Remove deprecated code in the specified version

### ESLint Deprecation Rules

The codebase uses ESLint rules to enforce deprecation standards:

```typescript
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
```

This rule ensures proper handling of deprecated features and prevents inappropriate usage.

### Runtime Deprecation Warnings

Deprecated functionality includes runtime warnings that can be controlled by feature flags:

```typescript
// Example from vue.adapter.ts
this.app.config.globalProperties.$tc = function (...args) {
    if (window._features_.V6_8_0_0) {
        console.warn(
            'Deprecation Warning',
            'The $tc function is deprecated and will be removed in future versions. Please use $t instead.',
        );
    }
    return i18n.global.t(...fixI18NParametersOrder(args));
};
```

## Best Practices

### Feature Flag Naming

- Use uppercase naming convention: `NEW_FEATURE`, `V6_8_0_0`
- Include version numbers for version-specific flags: `V6_8_0_0`
- Use descriptive names that clearly indicate the feature: `USE_NEW_CHECKOUT_FLOW`

### Code Organization

1. **Avoid Nested Flags**: Don't nest feature flags within each other as it creates complex logic paths
2. **Document Removal Version**: Always include the target removal version in code comments
3. **Single Responsibility**: Each flag should control a single, well-defined feature
4. **Clean Boundaries**: Ensure clean separation between flagged and non-flagged code

### Migration Guidelines

1. **Gradual Migration**: Use feature flags to enable gradual migration from old to new implementations
2. **Backward Compatibility**: Maintain backward compatibility during the deprecation period
3. **Clear Documentation**: Provide clear migration guides and examples
4. **Testing**: Ensure both feature flag states (on/off) are properly tested

## Migration Path When Flag Removed

When removing a feature flag:

1. **Remove Flag Checks**: Remove all `Feature.isActive()` calls for the flag
2. **Remove Legacy Code**: Delete the old implementation that was being replaced
3. **Clean Up**: Remove any flag-specific configuration and documentation
4. **Update Tests**: Update tests to reflect the new default behavior
5. **Update Documentation**: Update all relevant documentation to reflect the changes

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

When writing tests, consider both flag states:

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
