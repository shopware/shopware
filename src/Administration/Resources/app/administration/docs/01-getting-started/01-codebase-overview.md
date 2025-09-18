# Codebase Overview

## How the Admin Fits into the Shopware Ecosystem

The Shopware Administration is a **Vue.js-based single-page application** that serves as the merchant backend interface for Shopware. It's built as a modular, extensible system that integrates deeply with:

- **Core Backend API**: All data operations go through Shopware's JSON:API-compliant REST API with automatic entity synchronization
- **Plugin System**: Extensions can add modules, components, and functionality using the same APIs as core features
- **Storefront**: Shares entity definitions and business logic concepts through unified data models
- **App Framework**: Third-party apps can extend admin functionality via extension APIs without requiring PHP code

**Why This Architecture Matters**: The admin is designed to be the central hub for all e-commerce operations while remaining completely extensible by plugins and apps without core modifications. This enables a rich ecosystem where third-party extensions feel native to the system.

**Real-world implications**:

- Plugins can add entire business modules (like advanced inventory management) that integrate seamlessly
- Apps can extend existing components (like adding custom fields to product forms) without touching core code
- The same data access patterns work for core features and extensions, ensuring consistency

## Project Structure Walkthrough

### High-Level Directory Structure

```text
src/Administration/Resources/app/administration/src/
├── app/           # Application layer - Vue.js integration & services
├── core/          # Core framework - factories, data handling, extension APIs
├── meta/          # Type definitions and metadata
└── module/        # Business logic modules (products, orders, customers, etc.)
```

### Core Framework (`src/core/`)

**What makes this unique**: Shopware uses a factory-based architecture with dependency injection, unlike typical Vue.js applications. This enables runtime extensibility and consistent APIs across the entire system.

```text
core/
├── factory/       # Factory pattern for creating instances
│   ├── module.factory.ts      # Creates and registers modules
│   ├── async-component.factory.ts   # Component registration system with lazy loading
│   ├── service.factory.ts     # Service container management (like Angular's DI)
│   ├── state.factory.ts       # Vuex store management
│   ├── repository-factory.ts  # Data access layer factory
│   └── locale.factory.ts      # Internationalization system
├── data/          # Data layer abstractions
│   ├── repository-factory.data.ts  # API repository pattern implementation
│   ├── entity-definition.factory.ts # Entity schema definitions
│   ├── changeset-generator.data.ts  # Tracks entity changes for API sync
│   └── error-resolver.data.ts       # API error handling
├── service/       # Core services (API, validation, etc.)
│   ├── api.service.ts         # HTTP client with authentication
│   ├── login.service.ts       # Authentication management
│   └── validation.service.ts  # Form validation rules
├── helper/        # Utility functions
└── shopware.ts    # Main framework entry point - the "Shopware" global
```

**Key concepts**:

- **Bottle.js container**: Used for dependency injection throughout the application
- **Factory pattern**: Every major system (components, services, modules) uses factories for registration
- **Global Shopware object**: Provides unified access to all framework features
- **Entity definitions**: Centralized schema definitions that drive both API and UI behavior

### Application Layer (`src/app/`)

**Purpose**: Bridges Vue.js with Shopware's factory system and provides application-specific functionality.

```text
app/
├── adapter/       # Framework adapters (Vue.js integration)
│   ├── view/      # Vue.js framework adapter
│   └── composition-extension-system.ts  # New Composition API system
├── component/     # Global components (forms, data tables, etc.)
│   ├── base/      # Foundation components (sw-field, sw-button, etc.)
│   ├── form/      # Form-specific components
│   ├── data-grid/ # Table and list components
│   └── structure/  # Layout and navigation components
├── service/       # Application-level services (menu, ACL, features)
│   ├── menu.service.ts        # Navigation menu management
│   ├── acl.service.ts         # Access control and permissions
│   ├── feature.service.ts     # Feature flag management
│   └── search-type.service.ts # Global search functionality
├── state/         # Vuex store modules
│   ├── session.store.ts       # User session and authentication
│   ├── notification.store.ts  # Toast notifications
│   └── error.store.ts         # Global error handling
├── init/          # Application initialization phases
│   ├── component.init.ts      # Register all components
│   ├── service.init.ts        # Initialize services
│   └── state.init.ts          # Setup Vuex stores
├── init-pre/      # Pre-initialization (runs before main init)
├── init-post/     # Post-initialization (runs after main init)
└── main.ts        # Application bootstrap and startup
```

**Initialization flow**:

1. **Pre-init**: Basic setup, feature flags, early service registration
2. **Main init**: Component registration, service initialization, route setup
3. **Post-init**: Final configuration, startup listeners, ready state

### Module System (`src/module/`)

**Key Insight**: Each business domain is a self-contained module with its own components, routes, and state. Modules are the primary way to organize business logic and can be added by core or plugins.

```text
module/
├── sw-product/    # Product management
│   ├── page/      # Route components (list, detail, create)
│   │   ├── sw-product-list/           # Product listing page
│   │   ├── sw-product-detail/         # Product detail/edit page
│   │   └── sw-product-create/         # Product creation page
│   ├── component/ # Reusable components
│   │   ├── sw-product-basic-form/     # Product basic information form
│   │   ├── sw-product-price-form/     # Pricing configuration
│   │   └── sw-product-variants/       # Variant management
│   ├── view/      # Sub-views for complex pages
│   ├── acl/       # Permission definitions
│   │   └── index.js                   # ACL rules and privilege definitions
│   ├── snippet/   # Translations for this module
│   │   ├── de-DE.json                 # German translations
│   │   └── en-GB.json                 # English translations
│   └── index.js   # Module registration and configuration
├── sw-order/      # Order management
├── sw-customer/   # Customer management
├── sw-cms/        # Content Management System
├── sw-settings/   # Global settings
└── ...            # 50+ core modules
```

**Module anatomy example** (`sw-product/index.js`):

```javascript
Shopware.Module.register('sw-product', {
    type: 'core',
    name: 'product',
    title: 'sw-product.general.mainMenuItemGeneral',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'product',

    routes: {
        index: {
            components: {
                default: 'sw-product-list'
            },
            path: 'index'
        },
        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.product.index'
            }
        }
    },

    navigation: [{
        id: 'sw-catalogue',
        label: 'sw-product.general.mainMenuItemGeneral',
        color: '#57D9A3',
        path: 'sw.product.index',
        icon: 'default-symbol-products',
        position: 20
    }],

    defaultSearchConfiguration
});
```

**Module benefits**:

- **Self-contained**: All related functionality in one place
- **Lazy-loaded**: Components only load when needed
- **Extensible**: Plugins can extend or override any module
- **Consistent**: Same structure across all business domains

## Key Architectural Patterns You'll Encounter Daily

### 1. Factory Pattern + Dependency Injection

**Why**: Enables plugin extensions and testing without tight coupling. Services are registered once and can be accessed globally with consistent APIs.

```javascript
// Registering a service (typically in plugin's main.js)
Shopware.Service.register('customProductService', () => {
    return {
        calculatePrice(product, context) {
            // Custom pricing logic
            return basePrice * taxRate;
        },
        
        async validateProduct(product) {
            // Custom validation
            return { isValid: true, errors: [] };
        }
    };
});

// Using the service anywhere in the application
const customService = Shopware.Service.get('customProductService');
const price = customService.calculatePrice(product, context);

// Built-in services you'll use frequently
const apiService = Shopware.Service.get('loginService');
const repositoryFactory = Shopware.Service.get('repositoryFactory');
const httpClient = Shopware.Service.get('httpClient');
```

**Common service patterns**:

- **Singleton pattern**: Services are created once and reused
- **Interface consistency**: All services follow similar API patterns
- **Plugin extensibility**: Plugins can register new services or override existing ones

### 2. Module Registration System

**Pattern**: Each module declares its routes, components, and permissions in `index.js`. This creates a declarative system where the framework automatically handles routing, navigation, and access control.

```javascript
// module/sw-product/index.js - Complete module registration
Shopware.Module.register('sw-product', {
    type: 'core',  // or 'plugin' for extensions
    name: 'product',
    title: 'sw-product.general.mainMenuItemGeneral',
    description: 'Manage your product catalog',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    
    // Route definitions
    routes: {
        index: {
            component: 'sw-product-list',
            path: 'index',
            meta: {
                privilege: 'product.viewer'  // ACL integration
            }
        },
        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id',
            props: { default: (route) => ({ productId: route.params.id }) },
            meta: {
                privilege: 'product.editor',
                parentPath: 'sw.product.index'
            }
        },
        create: {
            component: 'sw-product-detail',
            path: 'create',
            meta: {
                privilege: 'product.creator'
            }
        }
    },

    // Navigation menu integration
    navigation: [{
        id: 'sw-catalogue',
        label: 'sw-product.general.mainMenuItemGeneral',
        color: '#57D9A3',
        path: 'sw.product.index',
        icon: 'default-symbol-products',
        position: 20,
        privilege: 'product.viewer'
    }],

    // Search integration
    defaultSearchConfiguration: {
        entityName: 'product',
        placeholderSnippet: 'sw-product.general.placeholderSearchBar',
        listingRoute: 'sw.product.index'
    }
});
```

**What happens automatically**:

- Routes are registered with Vue Router
- Navigation entries appear in the main menu
- ACL privileges are enforced
- Search integration is configured
- Lazy loading is handled by the framework

### 3. Repository Pattern for Data Access

**Purpose**: Abstracts API calls and provides consistent data access patterns. All entity operations go through repositories, which handle caching, validation, and API communication.

```javascript
// Getting a repository for any entity
const productRepository = Shopware.Service.get('repositoryFactory')
    .create('product');

// Create search criteria (similar to SQL WHERE clauses)
const criteria = new Shopware.Data.Criteria();
criteria.addFilter(Shopware.Data.Criteria.equals('active', true));
criteria.addFilter(Shopware.Data.Criteria.contains('name', 'Laptop'));
criteria.addSorting(Shopware.Data.Criteria.sort('createdAt', 'DESC'));
criteria.setPage(1);
criteria.setLimit(25);

// Add associations (like SQL JOINs)
criteria.addAssociation('categories');
criteria.addAssociation('manufacturer');
criteria.addAssociation('media');

// Search with criteria
const context = Shopware.Context.api;
const result = await productRepository.search(criteria, context);

// Access results
console.log(`Found ${result.total} products`);
result.forEach(product => {
    console.log(`Product: ${product.name}`);
    console.log(`Categories: ${product.categories.map(c => c.name).join(', ')}`);
});

// CRUD operations
const newProduct = productRepository.create(context);
newProduct.name = 'New Product';
newProduct.productNumber = 'PROD-001';

// Save changes (automatically handles API calls)
await productRepository.save(newProduct, context);

// Update existing
const existingProduct = await productRepository.get(productId, context);
existingProduct.price = [{ currencyId: '...', gross: 99.99 }];
await productRepository.save(existingProduct, context);

// Delete
await productRepository.delete(productId, context);
```

**Repository features**:

- **Automatic change tracking**: Only modified fields are sent to API
- **Validation integration**: Client-side validation before API calls  
- **Error handling**: Consistent error responses across all entities
- **Caching**: Intelligent caching for frequently accessed data
- **Associations**: Easy loading of related entities

### 4. Component Extension System

**Two approaches currently coexist**:

- **Current (Options API + Twig-like overrides)**: Legacy extension system
- **Future (Composition API + createExtendableSetup)**: Modern extension approach

```javascript
// Current: Component override (Options API)
Shopware.Component.override('sw-product-list', {
    methods: {
        // Override existing method
        loadProducts() {
            // Custom implementation
        }
    }
});

// Future: Composition API extension (Experimental)
Shopware.Component.overrideComponentSetup('sw-product-list', (previousState, props, context) => {
    // Access previous component state
    const newPageSize = ref(50);
    
    // Override existing method while calling original
    const newLoadProducts = () => {
        previousState.loadProducts(); // Call original
        console.log('Custom logic after loading');
    };

    return {
        pageSize: newPageSize,     // Override property
        loadProducts: newLoadProducts  // Override method
    };
});
```

**How createExtendableSetup works**:

- Components use `createExtendableSetup` to expose public/private APIs
- Plugins use `overrideComponentSetup` to modify component behavior
- Provides access to `previousState`, `props`, and `context`
- Supports TypeScript for type safety

## Quick Reference: "I Want to Do X, Where Do I Look?"

### Adding New Business Functionality

- **Start**: Create new module in `src/module/sw-[your-feature]/`
- **Structure**: Copy pattern from existing modules like `sw-product` or `sw-customer`
- **Register**: Add module registration in module's `index.js`
- **Components**: Create page components in `page/` directory
- **Routes**: Define in module registration with proper ACL privileges
- **Navigation**: Add menu entries in module's navigation config

**Step-by-step example**:

```javascript
// 1. Create module structure
module/sw-inventory/
├── page/sw-inventory-list/
├── component/sw-inventory-card/
├── acl/index.js
└── index.js

// 2. Register module in index.js
Shopware.Module.register('sw-inventory', {
    routes: { /* your routes */ },
    navigation: { /* your menu */ }
});
```

### Creating Reusable Components

- **Global components**: `src/app/component/` (available everywhere)
- **Module-specific**: `src/module/[module-name]/component/` (scoped to module)
- **Registration**: Use `Shopware.Component.register()` or `Shopware.Component.extend()`
- **Base components**: Extend existing components like `sw-card`, `sw-field`

**Component registration patterns**:

```javascript
// Register new component
Shopware.Component.register('sw-custom-field', {
    template: `<input v-model="value" />`,
    props: ['value'],
    // ... component definition
});

// Extend existing component
Shopware.Component.extend('sw-enhanced-field', 'sw-field', {
    // Add new props or methods
});
```

### Data Operations

- **Entity definitions**: Already defined in core (`src/core/data/`)
- **API calls**: Always use repository pattern via `repositoryFactory`
- **State management**: Module-specific Vuex stores in `src/app/state/`
- **Validation**: Use `validationService` for form validation
- **Error handling**: Centralized through `errorStore`

**Data flow example**:

```javascript
// 1. Get repository
const repo = this.repositoryFactory.create('product');

// 2. Create criteria
const criteria = new Criteria();
criteria.addFilter(Criteria.equals('active', true));

// 3. Fetch data
const products = await repo.search(criteria, this.context);

// 4. Update component state
this.products = products;
```

### Adding Services

- **Core services**: `src/core/service/` (framework-level services)
- **App services**: `src/app/service/` (application-level services)
- **Registration**: Use `Shopware.Service.register()` in main.js or init files
- **Access pattern**: `Shopware.Service.get('serviceName')`

**Service creation example**:

```javascript
// Register service
Shopware.Service.register('inventoryService', () => {
    return {
        async calculateStock(productId) {
            // Service logic
        }
    };
});

// Use service
const service = Shopware.Service.get('inventoryService');
```

### Routing & Navigation

- **Route definitions**: In module's `index.js` file under `routes` property
- **Navigation entries**: Also in module registration under `navigation` property
- **Route components**: `src/module/[module-name]/page/[page-name]/`
- **Guards**: Use `meta.privilege` for ACL, custom guards in route definition
- **Parameters**: Access via `this.$route.params` in components

### Styling & UI

- **Global styles**: `src/app/assets/scss/` (affects entire application)
- **Component styles**: Scoped in individual `.vue` files
- **Design system**: Uses Shopware's Meteor Component Library
- **SCSS variables**: Available globally for consistent theming
- **Component library**: Use existing components before creating new ones

**Styling hierarchy**:

1. Meteor Component Library (base styles)
2. Global SCSS variables and mixins
3. Component-specific styles
4. Utility classes

### Plugin Development

- **Extension points**: Components, services, modules all support extensions
- **API**: Use `Shopware.*` global for accessing factory systems
- **Modern approach**: Prefer Composition API for new components
- **Override vs Extend**: Use `override` to replace, `extend` to inherit
- **Main entry**: Plugin's `main.js` file in administration resources

**Plugin structure**:

```text
<plugin>/src/Resources/app/administration/src/
├── main.js                    # Entry point
├── module/[new-module]/       # New modules
├── component/[override]/      # Component overrides
└── service/[custom-service]/  # Custom services
```

### Debugging & Development

- **Main instance**: `window.Shopware` in browser console
- **Factory inspection**: `Shopware.Component.getComponentRegistry()`
- **Service inspection**: `Shopware.Service.list()`
- **State inspection**: Use Vue DevTools for Vuex state
- **Network debugging**: Check API calls in Network tab
- **Error logging**: Errors are logged to console with context

**Debugging commands**:

```javascript
// In browser console
Shopware.Component.getComponentRegistry(); // All components
Shopware.Service.list(); // All services
Shopware.Module.getModuleRegistry(); // All modules
```

## What Makes This Architecture Unique

### 1. Factory-based Dependency Injection

Unlike typical Vue apps that import dependencies directly, Shopware uses a container-based system where everything is registered and retrieved through factories. This enables:

- **Runtime extensibility**: Plugins can replace or extend any service
- **Consistent APIs**: All systems follow the same registration/access patterns  
- **Testing isolation**: Easy to mock dependencies for unit tests
- **Plugin compatibility**: Multiple plugins can extend the same components safely

### 2. Plugin-first Design

Core features and plugins use identical extension APIs, ensuring that plugins feel native:

- **Same tools**: Plugins use the same factories, services, and patterns as core
- **No second-class citizens**: Plugin components integrate seamlessly with core UI
- **Consistent experience**: Users can't distinguish between core and plugin features
- **Future-proof**: Plugin APIs evolve with core APIs automatically

### 3. Module Self-containment

Each business domain is completely isolated with its own:

- **Components**: No shared state between unrelated modules
- **Routes**: Module-specific routing with automatic navigation
- **Permissions**: Fine-grained ACL per module and action
- **Translations**: Module-specific i18n files
- **State**: Isolated Vuex modules prevent conflicts

This prevents the "spaghetti code" problem common in large applications.

### 4. Dual Extension Systems

The coexistence of Options API and Composition API extension systems provides:

- **Backward compatibility**: Existing plugins continue working
- **Modern development**: New features can use Composition API
- **Gradual migration**: No big-bang rewrites required
- **Developer choice**: Teams can adopt new patterns at their own pace

### 5. Repository Pattern Everywhere

Consistent data access across the entire application through:

- **Entity abstraction**: Same interface for all data types (products, orders, customers)
- **Automatic optimization**: Intelligent caching and change tracking
- **Type safety**: Entity definitions provide structure and validation
- **Plugin extensibility**: Custom entities work exactly like core entities

### Real-world Benefits

**For Merchants**:

- Consistent UI experience across all features
- Powerful customization without breaking updates
- Rich ecosystem of compatible extensions

**For Developers**:

- Predictable patterns reduce learning curve
- Strong extension APIs enable complex customizations
- Type safety and tooling support boost productivity

**For Plugin Authors**:

- Same APIs as core team uses
- Deep integration capabilities
- Future-proof extension points

This foundation enables the extensive plugin ecosystem while maintaining code organization, testability, and consistent user experience across all features.
