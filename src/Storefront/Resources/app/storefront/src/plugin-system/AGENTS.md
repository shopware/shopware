# Plugin System Internals

**Scope**: Plugin system implementation details and internal mechanics. For application-level overview, see parent `../../AGENTS.md`.

Core infrastructure for the Storefront plugin system.

## Components

| Component               | File                       | Purpose                                       |
|-------------------------|----------------------------|-----------------------------------------------|
| **PluginManager**       | `plugin.manager.js`        | Registry, lifecycle management, async loading |
| **PluginBaseClass**     | `plugin.class.js`          | Base class all plugins extend                 |
| **PluginRegistry**      | `plugin.registry.js`       | Internal registry storage                     |
| **PluginConfigManager** | `plugin.config.manager.js` | Config resolution from data attributes        |

**Global Access** (recap from parent AGENTS.md):
```javascript
window.PluginManager        // Singleton
window.PluginBaseClass      // Base class reference
```

## Lifecycle

| Phase              | Trigger                                         | Action                                                |
|--------------------|-------------------------------------------------|-------------------------------------------------------|
| **Registration**   | `register(name, class, selector, options)`      | Store class/promise in registry                       |
| **Initialization** | DOM ready, `initializePlugins()`                | Fetch async plugins, instantiate on matching elements |
| **Update**         | DOM changes, `initializePlugin(name, selector)` | Call `update()` on existing instances or create new   |

## Mechanisms

### Async Loading
- Plugins registered with `() => import('./plugin.js')` marked as async
- `_fetchAsyncPlugins()` uses `Promise.all()` to load plugins matching DOM selectors
- Only loads when selector matches elements (performance optimization)

### Extension
- Creates `InternallyExtendedPlugin extends parentPlugin`
- Prototype merge: `Object.assign(Extended.prototype, newClass)`
- Same name = override, different name = new plugin alongside parent

### Instance Storage
- Instances stored on element: `el.__plugins = Map()`
- Retrieve: `getPluginInstanceFromElement(el, name)`

## Component Responsibilities

- **PluginManager**: Registration, initialization coordination, async loading, extension
- **PluginBaseClass**: Option merging, instance registration, lifecycle hooks (`init()`/`update()`)
- **PluginRegistry**: Storage (Map), lookup, multiple selectors per plugin
- **PluginConfigManager**: Named config resolution from data attributes
