# Plugins

Plugins are the most powerful extension mechanism for self-hosted Shopware instances, providing direct access to the administration's runtime environment and APIs.

## Plugin Architecture

### Runtime Integration
- **Direct JavaScript Injection**: Plugin code executes within the administration bundle
- **Pre-Mount Registration**: Components, services, and modules register before Vue app initialization
- **Global Shopware Object**: Central registry for all extension points

### Core Extension Points

#### 1. Component System
```javascript
// Register new component
Shopware.Component.register('my-custom-component', {
    template: '<div>{{ message }}</div>',
    data() {
        return {
            message: 'Hello from plugin'
        };
    }
});

// Extend existing component (creates new component)
Shopware.Component.extend('my-enhanced-field', 'sw-text-field', {
    computed: {
        additionalClasses() {
            return ['my-custom-class', ...this.$super('additionalClasses')];
        }
    }
});

// Override existing component (replaces original)
Shopware.Component.override('sw-text-field', {
    methods: {
        onInput() {
            // Call original method
            this.$super('onInput');
            
            // Add custom logic
            this.validateCustomRules();
        }
    }
});
```

#### 2. Service Registration
```javascript
// Register custom service
Shopware.Service().register('myCustomService', () => {
    return {
        processData(data) {
            // Custom business logic
            return transformedData;
        }
    };
});

// Decorate existing service
const originalApiService = Shopware.Service('apiService');
Shopware.Service().register('apiService', () => {
    return {
        ...originalApiService,
        request(config) {
            // Add authentication headers
            config.headers = {
                ...config.headers,
                'X-Custom-Auth': this.getAuthToken()
            };
            
            return originalApiService.request(config);
        }
    };
});
```

#### 3. Module Registration
```javascript
// Register complete new module
Shopware.Module.register('my-custom-module', {
    type: 'plugin',
    name: 'custom-module',
    title: 'My Custom Module',
    description: 'Custom functionality for specific business needs',
    
    routes: {
        index: {
            component: 'my-custom-index',
            path: 'index'
        },
        detail: {
            component: 'my-custom-detail',
            path: 'detail/:id'
        }
    },
    
    navigation: [{
        id: 'my-custom-module',
        label: 'Custom Module',
        color: '#ff3d58',
        path: 'my.custom.module.index',
        icon: 'regular-products',
        parent: 'sw-catalogue',
        position: 100
    }]
});
```

## Current Extension Systems

### 1. Component Factory System

```javascript
// Method extension with super calls
Shopware.Component.override('sw-product-detail', {
    methods: {
        saveProduct() {
            // Pre-save validation
            if (!this.validateCustomFields()) {
                return;
            }
            
            // Call original save method
            return this.$super('saveProduct').then(() => {
                // Post-save actions
                this.sendAnalyticsEvent('product_saved');
            });
        },
        
        validateCustomFields() {
            // Custom validation logic
            return this.product.customFields?.requiredField?.length > 0;
        }
    }
});
```

### 2. TwigJS Block System

```twig
{# Override specific template block #}
{% block sw_product_detail_content_tabs_advanced %}
    {% parent %}
    
    <sw-card title="Custom Configuration">
        <my-custom-component :product="product" />
    </sw-card>
{% endblock %}
```

### 3. Native Block System (Future)

- New template extensions
- Future-proof development
- Enhanced flexibility
- Native Vue component
- Better performance
- Works with SFC

**Implementation:**
```html
<!-- In component template -->
<sw-block name="product-detail-tabs">
    <sw-tabs>
        <sw-block name="product-detail-tab-basic">
            <sw-tabs-item>Basic Information</sw-tabs-item>
        </sw-block>
        
        <sw-block name="product-detail-tab-advanced">
            <sw-tabs-item>Advanced Settings</sw-tabs-item>
        </sw-block>
    </sw-tabs>
</sw-block>

<!-- In plugin override -->
<sw-block name="product-detail-tabs" extends="product-detail-tabs">
    <sw-block-parent />
    
    <sw-block name="product-detail-tab-custom">
        <sw-tabs-item>Custom Configuration</sw-tabs-item>
    </sw-block>
</sw-block>
```

### 4. Composition API Extensions

> **Experimental** — available behind feature flag `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`, stable in v6.8.0.
> See [04-composition-extension-system.md](./04-composition-extension-system.md) for the full technical reference.
> `createExtendableSetup()` and `overrideComponentSetup()` are `@private` since 6.8: prefer native setup SFCs ([07-native-setup-authoring.md](./07-native-setup-authoring.md)), which compile to these calls. The examples below show the underlying mechanism.

Components migrated to Composition API expose a typed public API that extensions can override with full type safety. There are two roles: **component author** (uses `createExtendableSetup`) and **plugin author** (uses `overrideComponentSetup`).

#### Component Author: Making a Setup Extendable

Components opt into the extension system by wrapping their setup with `createExtendableSetup`. The setup return value is split into `public` (accessible to overrides) and `private` (internal only, available as `_private`):

```typescript
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { ref, computed } from 'vue';

// Register the component's public API shape in the global type map
declare global {
    interface ComponentPublicApiMapping {
        'sw-product-list': {
            columns: Ref<Column[]>;
            totalCount: Ref<number>;
            loadData: () => Promise<void>;
        };
    }
}

export default {
    name: 'sw-product-list',
    props: { showDrafts: Boolean },
    setup(props) {
        return createExtendableSetup(
            { name: 'sw-product-list', props },
            (props) => {
                const columns = ref<Column[]>([]);
                const totalCount = ref(0);
                const internalCursor = ref<string | null>(null); // kept private

                const loadData = async () => { /* ... */ };

                return {
                    public: { columns, totalCount, loadData },
                    private: { internalCursor },
                };
            },
        );
    },
};
```

Rules for `createExtendableSetup`:
- The `originalSetup` callback **must** return `{ public?, private? }` — at least one is required.
- A setup binding must **not** share its name with a prop (throws in development).
- `public` properties form the override API; `private` properties are accessible in overrides via `previousState._private`.

#### Plugin Author: Overriding Component Setup

```javascript
import { ref, computed } from 'vue';

Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState, props, context) => {
    const customFilters = ref([]);
    const isCustomMode = ref(false);

    // Extend an existing computed property — reads from the previous state ref
    const columns = computed(() => {
        const baseColumns = previousState.columns.value;

        if (isCustomMode.value) {
            return [
                ...baseColumns,
                { property: 'customScore', label: 'Custom Score', sortable: true },
            ];
        }

        return baseColumns;
    });

    // Override an existing method, calling the previous implementation
    const loadData = async () => {
        // Apply custom filters before delegating to the previous implementation
        customFilters.value.forEach(f => { /* apply */ });
        return previousState.loadData();
    };

    // Access private state (not part of the public API)
    const cursor = previousState._private.internalCursor;

    return {
        columns,          // replaces the existing binding
        loadData,         // replaces the existing function
    };
});
```

#### Supported Override Return Types

| Return value | Behavior |
|---|---|
| Plain `ref` replacing a writable ref | 2-way synced with the existing ref, which stays in the component state |
| Any non-ref value (primitive, object, function) replacing a writable ref | Assigned to the existing ref's `.value` |
| A `computed`, readonly or custom ref | Replaces the existing binding |
| Any value replacing a binding that is not a writable ref (function, readonly computed, `reactive` object, …) | Replaces the existing binding. A `reactive` object is replaced, not merged; development builds warn about missing nested keys |

Returning a prop key in the override result logs a console error and the value is ignored. Returning a key the component does not provide adds it and logs a development warning; keep helper state such as `customFilters` local to the override instead. An override that throws is reported through Vue's error handler, and the other overrides still apply.

All overrides are applied once, synchronously, while the component is set up: Composition API overrides first, in registration order, then Options API overrides. Register them from your plugin's entry file, before the Administration mounts.

#### Options API Backward-Compatibility Shim

When a plugin overrides a Composition API component using old-style Options API patterns (e.g. `data`, `methods`, `computed`, `watch`, mixins, or lifecycle hooks), the administration **automatically activates a compatibility shim** instead of throwing an error. A deprecation warning is logged to the browser console.

**Shim activation** — the shim is enabled when the override config contains any of:
`data`, `methods`, `computed`, `watch`, `mixins`, `inject`, `extends`, or any lifecycle hook name. Each override is converted once per component and then runs inside the component's `setup()` with the real instance, so `this.$t`, `inject` and every lifecycle hook work.

**Supported Options API features:**

| Feature | Notes |
|---|---|
| `data()` | Each key becomes a `ref` |
| `methods` | Bound to a `this` proxy |
| `computed` | Converted to `computed()` refs; supports getter-only and getter+setter |
| `watch` | Registered via `watch()`. Dot-notation paths are **not** supported |
| `inject` | Array, `{ localKey: 'provideKey' }`, and `{ localKey: { from, default } }` forms |
| `mixins` | Flattened depth-first (deepest ancestor first), then merged |
| Lifecycle hooks | `beforeCreate`, `created`, `beforeMount`, `mounted`, `beforeUpdate`, `updated`, `beforeUnmount`, `unmounted`, `activated`, `deactivated`, `errorCaptured` |

**`this` proxy resolution order** inside shim methods/computed/watchers:
1. `$super`, and `$`-prefixed instance properties (`$t`, `$emit`, `$router`, …)
2. Local state (`data`, `computed`, `methods` from the override itself)
3. Injected values (`inject`)
4. Props
5. `previousState` (the component's existing Composition API state)

`this.$super('methodName', ...args)` calls the method on `previousState`. For computed refs, `$super` unwraps the ref value.

**Unsupported options (logged as warnings and ignored):**

| Option | Level | Reason |
|---|---|---|
| `components`, `directives` | `console.warn` | Component/directive registration belongs to the component definition itself, not to overrides. Register them in the component config instead. |
| `provide` | `console.warn` | The provide/inject contract is established at component setup time. Overriding it after the fact could silently break descendant injections in unpredictable ways. |
| `template` | `console.warn` | Composition API components already have a compiled template; replacing it via an override would bypass Vue's template compiler pipeline and conflict with the existing render function. |
| `extends` | `console.warn` | `extends` creates implicit inheritance chains that are difficult to merge reliably with Composition API state. Use `mixins` or explicit override methods instead. |
| `inheritAttrs`, `emits` | `console.warn` | These are component-level declarations that affect how Vue compiles and validates the component. They cannot be changed at override time without re-compiling the component. |
| `render()` | `console.error` — component will not work correctly | A custom render function completely replaces the compiled template. The shim cannot reconcile a custom render function with the existing Composition API template, so the component will break. |
| Dot-notation `watch` paths (e.g. `'a.b.c'`) | `console.warn` — watcher is skipped | Resolving nested reactive paths requires deep traversal of the Composition API state graph, which adds significant complexity for a pattern that is rarely used in plugins or core code. |

#### Migration Guide: Options API Override → `overrideComponentSetup`

```javascript
// Before — Options API override (triggers shim + deprecation warning)
Shopware.Component.override('sw-product-list', {
    data() {
        return { isCustomMode: false };
    },
    computed: {
        columns() {
            const base = this.$super('columns');
            return this.isCustomMode ? [...base, { property: 'score' }] : base;
        },
    },
    methods: {
        async loadData() {
            await this.$super('loadData');
            this.isCustomMode = true;
        },
    },
});

// After — native Composition API override (no shim, fully typed)
Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState) => {
    const isCustomMode = ref(false);

    const columns = computed(() =>
        isCustomMode.value
            ? [...previousState.columns.value, { property: 'score' }]
            : previousState.columns.value,
    );

    const loadData = async () => {
        await previousState.loadData();
        isCustomMode.value = true;
    };

    // isCustomMode stays local: the component has no such binding to replace
    return { columns, loadData };
});
```

## Advanced Patterns

### State Management Integration
```javascript
// Register custom Vuex module
Shopware.State.registerModule('myCustomModule', {
    namespaced: true,
    state: {
        customData: []
    },
    mutations: {
        setCustomData(state, data) {
            state.customData = data;
        }
    },
    actions: {
        async loadCustomData({ commit }) {
            const data = await this._vm.$api.get('/custom-endpoint');
            commit('setCustomData', data);
        }
    }
});
```

### Repository Decoration
```javascript
// Enhance existing repository
const originalProductRepository = Shopware.Service('repositoryFactory').create('product');

Shopware.Service().register('productRepository', () => {
    return {
        ...originalProductRepository,
        
        async save(entity, context) {
            // Pre-save processing
            this.processCustomFields(entity);
            
            // Call original save
            const result = await originalProductRepository.save(entity, context);
            
            // Post-save actions
            this.triggerWebhooks(entity);
            
            return result;
        }
    };
});
```
## Best Practices

### 1. Component Extension Hierarchy
```javascript
// Good: Clear extension chain
Shopware.Component.extend('my-base-field', 'sw-text-field', {
    // Base enhancements
});

Shopware.Component.extend('my-specific-field', 'my-base-field', {
    // Specific functionality
});

// Avoid: Deep override chains that are hard to debug
```

### 2. Service Decoration
```javascript
// Good: Preserve original interface
const originalService = Shopware.Service('originalService');
Shopware.Service().register('originalService', () => ({
    ...originalService,
    enhancedMethod(data) {
        const processed = this.preProcess(data);
        return originalService.originalMethod(processed);
    }
}));
```

### 3. Error Handling
```javascript
Shopware.Component.override('sw-entity-detail', {
    methods: {
        async saveEntity() {
            try {
                const result = await this.$super('saveEntity');
                this.onSaveSuccess(result);
                return result;
            } catch (error) {
                this.handleSaveError(error);
                throw error; // Re-throw to preserve original behavior
            }
        }
    }
});
```

## Limitations & Drawbacks

### Current System Challenges
1. **Complex Override Chains**: Deep extension hierarchies are difficult to debug
2. **Runtime Template Compilation**: TwigJS blocks require runtime processing
3. **Breaking Changes**: Almost every core change can break extensions
4. **TypeScript Support**: Limited type safety with Options API extensions
5. **Performance Impact**: Runtime template compilation and deep override chains

### Migration Considerations
- **Gradual Migration**: Both systems will coexist during transition period
- **Testing Requirements**: Extensive testing needed when migrating extension patterns
- **Documentation Updates**: Plugin documentation must cover multiple approaches
- **Developer Training**: Teams need to learn new extension patterns

## Future Roadmap

The plugin system is evolving toward:
1. **Full Composition API**: Native Vue 3 patterns with better TypeScript support
2. **Native Blocks**: Complete replacement of TwigJS template system
3. **Enhanced Developer Experience**: Better debugging and development tools
4. **Improved Performance**: Elimination of runtime compilation overhead

For current development, continue using the Component Factory system for stability while preparing for future migration to Composition API extensions.
