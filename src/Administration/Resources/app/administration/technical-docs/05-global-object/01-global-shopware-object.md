# Global Shopware Object

The global Shopware object serves as the central registry and facade for core extensibility in the Shopware 6 Administration. It provides a unified interface for plugin authors and core developers to access various registries, services, and utilities.

## Why a Global Object?

The global Shopware object exists for **practical reasons**:

1. **Plugin Developer Experience**: Provides a simple, predictable API that doesn't require complex dependency injection knowledge
2. **Backward Compatibility**: Maintains API stability across Shopware versions for existing plugins
3. **Discoverability**: Easy to explore available APIs via `window.Shopware` in browser dev tools
4. **Universal Access**: Available everywhere without imports or injection - crucial for runtime plugin loading

The object is exposed as `window.Shopware` during application bootstrap, making it globally accessible to all code running in the administration.

## Core Structure

The global Shopware object exposes the following essential registries:

### Component System (Most Important)
```javascript
Shopware.Component = {
    register: Function,    // Register new components - primary plugin API
    extend: Function,      // Extend existing components
    override: Function,    // Override existing components
    // ...additional methods
}
```

### Module System
```javascript
Shopware.Module = {
    register: Function,           // Register new admin modules
    getModuleRegistry: Function,  // Get all registered modules
    // ...additional methods
}
```

### Service Layer
```javascript
Shopware.Service = ServiceFactory; // Service factory for dependency injection
```

### State Management
```javascript
Shopware.State = StateFactory(); // Legacy Vuex state (deprecated)
Shopware.Store = Store.instance; // Current Pinia store instance
```

### API Services
```javascript
Shopware.ApiService = {
    register: Function,    // Register API services
    getByName: Function,   // Get API service by name
    // ...additional methods
}
```

### Feature Flags
```javascript
Shopware.Feature = {
    isActive: Function   // Check if feature is active - commonly used
}
```

### Essential Utilities
```javascript
Shopware.Utils = utils;           // Collection of utility functions
Shopware.Data = data;             // DAL utilities and repositories
Shopware.Context = useContext();  // Current application context
Shopware.Defaults = { /* ... */ }; // System default IDs and values
```

### Composables (experimental)
```javascript
Shopware.Composables = composables; // The composables that replace mixins, e.g. useListing
```

`Shopware.Composables` holds the composables listed in `src/app/composables/index.ts`, every one of which
replaces a mixin. Like the composables themselves, it is
`@experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES`: names and signatures can still
change before 6.9. Extensions written in TypeScript or as SFCs import them from
[`shopware:composables`](04-virtual-modules.md) instead, which reads the same object.

```ts
// Before: Options API with the listing mixin
Shopware.Component.register('my-list', {
    mixins: [Shopware.Mixin.getByName('listing')],
    methods: {
        async getList() { /* load this.page / this.limit */ },
    },
});

// After: in <script setup> or setup()
import { useListing } from 'shopware:composables';

async function getList() { /* load page.value / limit.value */ }

const { page, limit, total, onPageChange } = useListing({ getList });
```

- Call a composable in `setup()` only, never in `created()` or other Options API hooks, and never in a
  component that still declares the mixin it replaces: both would register their watchers and loads.
- `Shopware.Composables` is assigned while `src/app/main` loads. `_swLoginOverrides` run earlier and
  cannot use it.
- The Administration imports each composable from its file. Replacing an entry of
  `Shopware.Composables` therefore changes what extensions get, not what core components run.
- In an extension's Jest specs, mock the module: `jest.mock('shopware:composables', () => ({ ... }))`.
- These mixins have no composable yet, so a component using one of them stays on the Options API:
  `generic-condition`, `sw-form-field`, `remove-api-error`, `discard-detail-page-changes`,
  `sw-settings-list`.

## Creation Process

1. **Initialization**: Created in `src/core/shopware.ts` as `ShopwareClass` singleton
2. **Container Setup**: Built on BottleJS dependency injection container
3. **Global Assignment**: Set as `window.Shopware` in `src/index.ts` during bootstrap
4. **Runtime Access**: Available immediately to all plugins and components

## Common Usage Patterns

### Plugin Registration (Primary Use Case)
```javascript
// Most common usage - registering components
Shopware.Component.register('my-plugin-component', {
    template: '<div>My Component</div>'
});

// Registering modules
Shopware.Module.register('my-plugin-module', {
    routes: { /* ... */ },
    navigation: { /* ... */ }
});
```

### Service Access
```javascript
const httpClient = Shopware.Service('httpClient');
const repositoryFactory = Shopware.Service('repositoryFactory');
```

### Feature Flag Checks
```javascript
if (Shopware.Feature.isActive('MY_FEATURE')) {
    // Feature-specific code
}
```

## Trade-offs

### Why Global is Beneficial Here
- **Plugin Ecosystem**: Thousands of plugins need consistent, simple API access
- **Runtime Loading**: Plugins load at runtime and need immediate API access
- **Developer Onboarding**: Lower barrier to entry for plugin developers
- **Debugging**: Easy inspection and testing in browser console

### Drawbacks
- **Testing Complexity**: Global dependencies make unit testing harder
- **Tree-shaking**: Harder to eliminate unused code
- **Tight Coupling**: Components become dependent on global state
- **Namespace Pollution**: Large global API surface

## Modern Alternatives

While the global object remains for compatibility, newer patterns include:
- **`shopware:*` modules**: `import { createId } from 'shopware:utils'` reaches the same global object
  through ordinary named imports. See [`shopware:*` Modules](04-virtual-modules.md).
- **Composables instead of mixins**: `Shopware.Composables.useListing()` or
  `import { useListing } from 'shopware:composables'`.
- **Composition API**: `useContext()`, service injection via composables
- **Direct Imports**: Import specific services/factories directly
- **Dependency Injection**: Use the underlying BottleJS container

The global object represents a **pragmatic compromise** between developer experience and modern architecture patterns, prioritizing ecosystem stability and ease of use for the large Shopware plugin community.
