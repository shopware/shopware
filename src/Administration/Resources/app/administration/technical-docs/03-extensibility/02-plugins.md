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

- Components migrated to Vue Composition API
- Type-safe extensions with TypeScript
- Advanced reactive patterns
- Native Vue features

**Modern Extension Pattern:**
```javascript
// Override component with Composition API
Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState, props, context) => {
    const customFilters = ref([]);
    const isCustomMode = ref(false);
    
    // Extend existing computed property
    const enhancedColumns = computed(() => {
        const baseColumns = previousState.columns.value;
        
        if (isCustomMode.value) {
            return [
                ...baseColumns,
                {
                    property: 'customScore',
                    label: 'Custom Score',
                    sortable: true
                }
            ];
        }
        
        return baseColumns;
    });
    
    // Override existing method
    const enhancedLoadData = async () => {
        // Apply custom filters
        const filters = [...previousState.filters.value, ...customFilters.value];
        
        // Call original load with enhanced filters
        return previousState.loadData(filters);
    };
    
    return {
        columns: enhancedColumns,
        loadData: enhancedLoadData,
        customFilters,
        isCustomMode
    };
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
