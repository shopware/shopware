# Plugin System Internals

**Scope**: Implementation details and internal mechanics of the plugin system infrastructure.

**Related Documentation**:
- Application overview → [`../../AGENTS.md`](../../AGENTS.md)
- Plugin development guide → [`../plugin/AGENTS.md`](../plugin/AGENTS.md)

## Components

| Component               | File                       | Purpose                                                          |
|-------------------------|----------------------------|------------------------------------------------------------------|
| **PluginManager**       | `plugin.manager.js`        | Registry, lifecycle management, async loading                    |
| **PluginBaseClass**     | `plugin.class.js`          | Base class all plugins extend                                    |
| **PluginRegistry**      | `plugin.registry.js`       | Internal registry storage                                        |
| **PluginConfigManager** | `plugin.config.manager.js` | Legacy named config registry (never adopted, see note below)     |

**Note**: `PluginConfigManager` exists but is not used (no configs ever registered). Use `data-{plugin-name}-options` instead.

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

### Options Merge Priority
Plugin options are merged in this order (highest priority last):
1. `static options` defined in plugin class
2. Options passed to `PluginManager.register(name, class, selector, options)`
3. Inline JSON from `data-{plugin-name}-options` attribute on element

**Implementation**: See `plugin.class.js` lines 99-122 for merge logic.

## Component Responsibilities

- **PluginManager**: Registration, initialization coordination, async loading, extension
- **PluginBaseClass**: Option merging, instance registration, lifecycle hooks (`init()`/`update()`)
- **PluginRegistry**: Storage (Map), lookup, multiple selectors per plugin
- **PluginConfigManager**: Named config resolution from data attributes
