# Core Concepts

## TODO Summary

This section covers the **essential architectural patterns** that enable 80% of daily work. These are the unique concepts every contributor must understand.

### Files to be created

#### `01-extension-systems.md`

- TODO: **CRITICAL**: The heart of Shopware's architecture - extension systems
- TODO: **CRITICAL**: Current: Twig blocks + Component.override/extend (Options API)
- TODO: **CRITICAL**: Future: Native Vue blocks + overrideComponentSetup (Composition API)
- TODO: **CRITICAL**: When and how to use each system in daily development
- TODO: **CRITICAL**: Real examples from the codebase

#### `02-factory-and-di-patterns.md`

- TODO: **Essential**: Factory pattern and BottleJS dependency injection in practice
- TODO: **Essential**: How services, components, and modules are registered and accessed
- TODO: **Essential**: The global Shopware instance and application lifecycle
- TODO: **Essential**: **WHY global Shopware object exists**: Plugin extensibility - allows plugins to access core services without imports
- TODO: **Essential**: Global vs imports: Why window.Shopware instead of ES6 imports for plugin ecosystem
- TODO: **Essential**: Plugin access patterns: How external plugins interact with core through global object
- TODO: **Essential**: Browser console debugging with window.Shopware
- TODO: **Essential**: Creating new services and components following established patterns

#### `03-module-architecture.md`

- TODO: **Essential**: Module-based architecture and organization
- TODO: **Essential**: How modules are discovered, loaded, and interact
- TODO: **Essential**: Module structure and file organization patterns
- TODO: **Essential**: Adding new functionality within existing modules

#### `04-entity-and-data-patterns.md`

- TODO: **Important**: Entity system and backend synchronization patterns
- TODO: **Important**: API integration and data flow architecture
- TODO: **Important**: Common data handling patterns in the codebase
