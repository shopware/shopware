# Storefront JavaScript App

Frontend JavaScript application for Shopware Storefront. Built with Webpack 5, Jest, native JavaScript.

Plugin-based architecture where plugins are classes instantiated on DOM elements, managed centrally by `window.PluginManager`.

**Context**: This documents the core storefront JS application (`src/Storefront/Resources/app/storefront/`). For adding JS to Shopware PHP extension plugins, the structure is different (`<plugin-root>/src/Resources/app/storefront/`).

## Project Structure

```
app/storefront/
├── src/                   # Source code
│   ├── plugin/            # Plugin implementations
│   ├── plugin-system/     # Core plugin system
│   ├── helper/            # Utility helpers
│   ├── service/           # Services (HTTP client)
│   ├── utility/           # UI utilities
│   ├── scss/              # Stylesheets
│   ├── vendor/            # Third-party dependencies
│   └── main.js            # Entry point
├── test/                  # Jest tests
├── webpack.config.js      # Webpack configuration
├── jest.config.js         # Jest configuration
└── package.json           # Dependencies
```

## Tech Stack

- **Framework**: Bootstrap 5 (UI components, utilities)
- **Build**: Webpack 5, Babel, PostCSS
- **Testing**: Jest, Playwright
- **Linting**: ESLint, Stylelint
- **Languages**: JavaScript, TypeScript (partial)

### Directory Guide

| Directory        | Purpose                           |
|------------------|-----------------------------------|
| `plugin/`        | Individual plugin implementations |
| `plugin-system/` | Core infrastructure               |
| `helper/`        | Foundational utilities            |
| `service/`       | Service classes                   |
| `utility/`       | UI-specific utilities             |
| `scss/`          | Stylesheets                       |

## Core Architecture

**Plugin Pattern**: Not Shopware plugins (extensions) - term dates back to jQuery ecosystem

**Plugin System Fundamentals**:
```javascript
window.PluginManager        // Registry and lifecycle manager
window.PluginBaseClass      // Base class for all plugins (same as import Plugin)
```

### Architecture Principles

**DOM-Driven**: Plugins initialize on elements matching `data-{plugin-name}="true"`

**Event-Based**: Communication via `this.$emitter` (element-scoped) or `document.$emitter` (global)

**Async-Ready**: Plugins can be lazy-loaded via dynamic imports

**One File Per Plugin**: Each plugin lives in its own file/module
- Example: `plugin/my-feature/my-feature.plugin.js`
- Single responsibility: one plugin class per file

**Multiple Plugins Per Element**: Multiple plugins can coexist on the same DOM element
- Example: `<div data-filter="true" data-ajax-modal="true">`
- Each plugin instance is independently managed

**Inheritance**: All plugins extend from `window.PluginBaseClass`
- Import pattern: `import Plugin from 'src/plugin-system/plugin.class'`
- Global reference: `window.PluginBaseClass` (same class, legacy access)
- Use ES6 imports in modern code: `class MyPlugin extends Plugin`

## Global Utilities

The application exposes several utilities on the `window` object for use in plugins and custom code:

| Utility                      | Purpose                                                        |
|------------------------------|----------------------------------------------------------------|
| `window.PluginManager`       | Plugin registration and lifecycle management                   |
| `window.PluginBaseClass`     | Base class for all plugins                                     |
| `window.PluginConfigManager` | **DO NOT USE** - Named configuration registry (non-functional) |
| `window.Feature`             | Feature flag system                                            |
| `window.eventEmitter`        | Global event emitter (document-scoped)                         |
| `window.focusHandler`        | Focus state management across navigation                       |
| `window.formValidation`      | Form validation utilities                                      |
| `window.bootstrap`           | Bootstrap 5 component API                                      |

**Note**: `window.eventEmitter` and `document.$emitter` are the same instance - use for global events across the application.

## Services

Backend communication and data fetching services.

| Service            | File                                 | Use For                                                   |
|--------------------|--------------------------------------|-----------------------------------------------------------|
| `AppClientService` | `service/app-client.service.ts`      | App System integration (auto-handles auth tokens/headers) |
| `fetch()`          | Native browser API                   | Store API calls (recommended for most backend requests)   |
| ~~`HttpClient`~~   | ~~`service/http-client.service.js`~~ | **Deprecated tag:v6.8.0** - Use fetch() instead           |

## Helpers

Utility helpers available for import. Use these to avoid reimplementing common functionality.

| Helper                  | Import Path                                | Purpose                                                           |
|-------------------------|--------------------------------------------|-------------------------------------------------------------------|
| `ArrowNavigationHelper` | `src/helper/arrow-navigation.helper`       | **Deprecated tag:v6.8.0** - Keyboard arrow navigation             |
| `CookieStorage`         | `src/helper/storage/cookie-storage.helper` | Cookie-based storage implementation                               |
| `DateFormatHelper`      | `src/helper/date.helper`                   | Format dates using Intl.DateTimeFormat                            |
| `Debouncer`             | `src/helper/debouncer.helper`              | Debounce function execution                                       |
| `DeviceDetection`       | `src/helper/device-detection.helper`       | Detect device type, browser, touch support                        |
| `DomAccess`             | `src/helper/dom-access.helper`             | **Deprecated tag:v6.8.0** - Use native browser API                |
| `ElementReplaceHelper`  | `src/helper/element-replace.helper`        | Replace DOM elements from HTML markup                             |
| `NativeEventEmitter`    | `src/helper/emitter.helper`                | Event pub/sub system (used by plugins)                            |
| `Feature`               | `src/helper/feature.helper`                | Feature flag system (available as `window.Feature`)               |
| `FocusHandler`          | `src/helper/focus-handler.helper`          | Focus state management (`window.focusHandler`)                    |
| `FormValidation`        | `src/helper/form-validation.helper`        | Form validation utilities (`window.formValidation`)               |
| `Iterator`              | `src/helper/iterator.helper`               | **Deprecated tag:v6.8.0** - Use native alternatives               |
| `MemoryStorage`         | `src/helper/storage/memory-storage.helper` | In-memory storage fallback                                        |
| `StorageSingleton`      | `src/helper/storage/storage.helper`        | Auto-fallback storage (localStorage→sessionStorage→cookie→memory) |
| `StringHelper`          | `src/helper/string.helper`                 | String manipulation (ucFirst, toDashCase, camelCase)              |
| `Vector`                | `src/helper/vector.helper`                 | Vector math operations                                            |
| `ViewportDetection`     | `src/helper/viewport-detection.helper`     | Viewport size detection and events                                |

**Note**: Helpers marked as deprecated should not be used in new code. Use native browser APIs instead.

## Build & Development

| Task                    | Command                        |
|-------------------------|--------------------------------|
| Build production assets | `composer build:js:storefront` |
| Hot reload development  | `composer watch:storefront`    |
| Install dependencies    | `composer init:js`             |

## Testing

| Task           | Command                          |
|----------------|----------------------------------|
| Run unit tests | `composer storefront:unit`       |
| Watch mode     | `composer storefront:unit:watch` |

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type | Check Command                   | Fix Command                         |
|-----------|---------------------------------|-------------------------------------|
| **JS/TS** | `composer eslint:storefront`    | `composer eslint:storefront:fix`    |
| **SCSS**  | `composer stylelint:storefront` | `composer stylelint:storefront:fix` |
| **Twig**  | `composer ludtwig:storefront`   | `composer ludtwig:storefront:fix`   |
