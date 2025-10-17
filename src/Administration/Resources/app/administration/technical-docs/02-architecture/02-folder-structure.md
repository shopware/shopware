 # Folder Structure

This document describes the folder structure of the Shopware 6 Administration interface, located in `src/Administration/Resources/app/administration/src`.

## Top-Level Structure

The administration source code is organized into four main directories:

```
src/Administration/Resources/app/administration/src/
├── app/          # Vue application layer
├── core/         # Framework utilities and low-level services
├── meta/         # Metadata, test datasets, and configuration
└── module/       # Business domain modules
```

## App Layer (`app/`)

The `app/` directory contains the Vue application layer, including entry points, bootstrap code, router configuration, and application initialization.

### Key Subdirectories:

- **`adapter/`** - View adapters (Vue.js integration)
- **`assets/`** - Static assets (SCSS styles, images, fonts)
- **`component/`** - Global application components
- **`composables/`** - Vue 3 composition API utilities
- **`decorator/`** - Service and component decorators
- **`directive/`** - Vue directives
- **`filter/`** - Vue filters for data transformation
- **`init/`** - Application initialization modules
- **`init-pre/`** - Pre-initialization hooks
- **`init-post/`** - Post-initialization hooks
- **`mixin/`** - Global Vue mixins
- **`plugin/`** - Plugin registration and management
- **`route/`** - Route definitions and guards
- **`service/`** - Application-level services
- **`snippet/`** - Translation strings and localization
- **`state/`** - Application state management
- **`store/`** - Vuex store modules

### Initialization Flow:

The application follows a three-phase initialization:
1. **Pre-initialization** (`init-pre/`) - Early setup, dependency registration
2. **Main initialization** (`init/`) - Core services, components, modules
3. **Post-initialization** (`init-post/`) - Final setup, cleanup

Key initialization modules include:
- `component.init.ts` - Component registration
- `router.init.ts` - Route setup
- `modules.init.ts` - Module loading
- `repository.init.ts` - Data layer setup
- `http.init.ts` - HTTP client configuration

## Core Layer (`core/`)

The `core/` directory contains framework utilities, low-level services, and foundational components that other layers depend on.

### Key Subdirectories:

- **`adapter/`** - Data adapters and API clients
- **`data/`** - Data access layer, entities, repositories
- **`factory/`** - Factory classes for creating services and components
- **`helper/`** - Utility functions and helper classes
- **`service/`** - Core services (authentication, API, validation)
- **`worker/`** - Web worker implementations

### Key Files:

- **`application.ts`** - Main application class
- **`shopware.ts`** - Core Shopware instance and API
- **`extension-api.ts`** - Extension/plugin API interface
- **`feature.ts`** - Feature flag management

### Core Services:

Essential services provided by the core layer:
- **Authentication** (`service/login.service.ts`)
- **API Communication** (`service/api/`)
- **Data Validation** (`service/validation.service.ts`)
- **Entity Mapping** (`service/entity-mapping.service.ts`)
- **JSON API Parsing** (`service/jsonapi-parser.service.ts`)
- **Timezone Handling** (`service/timezone.service.ts`)

### Factory Pattern:

The core uses extensive factory patterns for:
- **API Services** (`factory/api-service.factory.js`)
- **Components** (`factory/async-component.factory.ts`)
- **Modules** (`factory/module.factory.ts`)
- **HTTP Clients** (`factory/http.factory.js`)
- **State Management** (`factory/state.factory.ts`)

## Meta Layer (`meta/`)

Contains metadata, configuration files, and test datasets used throughout the application.

### Files:

- **`baseline.ts`** - Baseline configuration and constants
- **`data-sets.json`** - Test and demo datasets
- **`position-identifiers.json`** - UI positioning and layout identifiers
- **`meta.spec.js`** - Metadata tests

## Module Layer (`module/`)

The `module/` directory contains business domain modules representing different areas of the Shopware administration.

### Module Categories:

#### Core Business Modules:
- **`sw-product/`** - Product management
- **`sw-category/`** - Category management
- **`sw-customer/`** - Customer management
- **`sw-order/`** - Order processing
- **`sw-media/`** - Media library
- **`sw-manufacturer/`** - Manufacturer management

#### Content Management:
- **`sw-cms/`** - Content Management System
- **`sw-landing-page/`** - Landing page builder
- **`sw-mail-template/`** - Email template editor

#### Sales & Marketing:
- **`sw-promotion-v2/`** - Promotion management
- **`sw-product-stream/`** - Product streams
- **`sw-sales-channel/`** - Sales channel configuration

#### System & Configuration:
- **`sw-settings*/`** - Various system settings modules
- **`sw-users-permissions/`** - User and permission management
- **`sw-integration/`** - Third-party integrations

#### Utilities & Tools:
- **`sw-bulk-edit/`** - Bulk editing functionality
- **`sw-import-export/`** - Data import/export
- **`sw-extension/`** - Plugin and app management

### Standard Module Structure:

Each module typically follows this internal structure:

```
sw-[module-name]/
├── acl/                    # Access Control Lists and permissions
├── component/              # Module-specific components
├── page/                   # Main page components (list, detail views)
├── view/                   # Sub-views and tabs within pages
├── service/                # Module-specific services
├── state/                  # Module-specific state management
├── snippet/                # Module-specific translations
├── mixin/                  # Module-specific mixins
├── helper/                 # Module-specific utility functions
├── index.js               # Module entry point and registration
└── default-search-configuration.js  # Search configuration (if applicable)
```

### Module Registration:

Modules are registered through their `index.js` file, which typically:
- Imports and registers components
- Defines routes
- Sets up ACL permissions
- Configures search behavior
- Registers services and stores

## Plugin Extension Points

Plugins extend the administration through a similar structure located in `custom/plugins/[PluginName]/src/Resources/app/administration/src/`.

### Plugin Structure Example (SwagPayPal):

```
custom/plugins/SwagPayPal/src/Resources/app/administration/src/
├── app/                    # Plugin-specific app extensions
├── constant/               # Plugin constants
├── core/                   # Core service extensions
├── init/                   # Plugin initialization
├── mixin/                  # Plugin mixins
├── module/                 # Plugin modules and extensions
├── types/                  # TypeScript type definitions
├── main.ts                # Plugin entry point
└── global.types.ts        # Global type augmentations
```

### Plugin Integration:

Plugins integrate with the core administration by:
- **Extending existing modules** - Adding components, views, or functionality
- **Creating new modules** - Introducing entirely new business domains
- **Overriding services** - Replacing or extending core services
- **Adding components** - Providing reusable UI components
- **Registering routes** - Adding new navigation paths

### Extension Mechanisms:

- **Component Extension** - Override or extend existing components
- **Module Extension** - Add tabs, views, or modify existing modules
- **Service Decoration** - Wrap or replace existing services
- **Route Extension** - Add new routes or modify existing ones
- **State Extension** - Extend Vuex store modules

## Architecture Principles

### Separation of Concerns:

- **`app/`** - Application layer concerns (UI, routing, initialization)
- **`core/`** - Framework and infrastructure concerns
- **`module/`** - Business domain concerns
- **`meta/`** - Configuration and metadata concerns

### Dependency Flow:

```
Modules → App → Core
Plugins → Modules/App/Core
```

- Modules depend on app and core layers
- App layer depends on core layer
- Core layer has minimal external dependencies
- Plugins can extend any layer but should follow the same patterns
