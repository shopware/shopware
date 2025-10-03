# Plugin

Creating and modifying Storefront JavaScript plugins.

## Plugin Structure

```javascript
import Plugin from 'src/plugin-system/plugin.class';

export default class MyFeaturePlugin extends Plugin {
    static options = { url: '', timeout: 300 };

    init() { this._registerEvents(); }
    update() { /* Re-initialize after DOM changes */ }
}
```

**Base Class Reference**: `Plugin` (imported) is the same as `window.PluginBaseClass` (global reference)
- Preferred: Use ES6 import pattern shown above
- Legacy: `class MyPlugin extends window.PluginBaseClass {}`

### Instance Properties

| Property           | Type                 | Purpose                        |
|--------------------|----------------------|--------------------------------|
| `this.el`          | `HTMLElement`        | DOM element plugin is bound to |
| `this.$emitter`    | `NativeEventEmitter` | Event pub/sub on element       |
| `this.options`     | `Object`             | Merged configuration options   |
| `this._pluginName` | `String`             | Plugin name (kebab-case)       |

## Registration

Register in `main.js`:

```javascript
// Sync
window.PluginManager.register('MyFeature', MyFeaturePlugin, '[data-my-feature]');

// Async (preferred)
window.PluginManager.register('MyFeature', () => import('./plugin/my-feature/my-feature.plugin'), '[data-my-feature]');
```

## Initialization

**Data Attribute**: `data-{plugin-name}="true"` (kebab-case)

```html
<div data-my-feature="true"></div>
```

### Naming Convention

Plugin class names are converted to kebab-case data attributes:

1. Strip "Plugin" suffix
2. Convert PascalCase to kebab-case
3. Prefix with `data-`

**Examples**:
- `FilterPlugin` → `data-filter`
- `VariantSwitchPlugin` → `data-variant-switch`
- `MyCustomFeaturePlugin` → `data-my-custom-feature`
- `AjaxModalPlugin` → `data-ajax-modal`

### Data Attribute Values

CSS selector `[data-my-feature]` matches by attribute presence, not value. Use `="true"` by convention. Remove attribute entirely to prevent initialization.

## Options

**Pass from template**:
```html
<div data-my-feature="true" data-my-feature-options='{"url": "/api"}'></div>
```

**Best Practice**: Use Twig variables for options to allow extension in templates:
```twig
{% set myFeatureOptions = {
    url: '/api',
    timeout: 300
} %}
<div data-my-feature="true" data-my-feature-options='{{ myFeatureOptions|json_encode }}'></div>
```
Then extend the variable in child templates to modify options.

**Merge priority** (highest last):
1. `static options` in class
2. `PluginManager.register()` options
3. `data-{plugin-name}-config` attribute (named config from PluginConfigManager)
4. `data-{plugin-name}-options` attribute (inline JSON)

## Events

### Publishing Events

```javascript
// Element-scoped event (preferred for plugin-specific events)
this.$emitter.publish('onClick', { data: 'payload' });

// Global event (for cross-plugin/app-wide communication)
document.$emitter.publish('globalEvent', { data: 'payload' });
```

### Subscribing to Events

```javascript
// Subscribe to element-scoped event from within plugin
this.$emitter.subscribe('onClick', handler, { once: true, scope: this });

// Subscribe to element-scoped event from another plugin
const pluginInstance = window.PluginManager.getPluginInstanceFromElement(element, 'MyFeature');
pluginInstance.$emitter.subscribe('onClick', handler);

// Subscribe to global event
document.$emitter.subscribe('globalEvent', handler);

// Unsubscribe: this.$emitter.unsubscribe('eventName')
// Remove all: this.$emitter.reset()
```

### Event Scope Guidance

| Use Case                     | Emitter             | Reasoning                                |
|------------------------------|---------------------|------------------------------------------|
| Plugin internal state change | `this.$emitter`     | Scoped to plugin's element, no pollution |
| User interaction on element  | `this.$emitter`     | Other plugins on same element may react  |
| Cross-plugin communication   | `document.$emitter` | Global scope, any plugin can subscribe   |
| App-wide state changes       | `document.$emitter` | Cart updates, user login, global modals  |

**Rule of thumb**: Start with `this.$emitter` (element-scoped), only use `document.$emitter` when multiple unrelated components need the event.

## Extension

```javascript
// Extend
import VariantSwitchPlugin from 'src/plugin/variant-switch/variant-switch.plugin';
export default class CustomVariantSwitch extends VariantSwitchPlugin {
    init() { super.init(); this._custom(); }
}
window.PluginManager.extend('VariantSwitch', 'CustomVariantSwitch', CustomVariantSwitch, '[data-variant-switch]');

// Override (same name)
window.PluginManager.override('VariantSwitch', CustomVariantSwitch, '[data-variant-switch]');
```

**Note**: Only ONE plugin can override another. If multiple PHP extensions try to override the same plugin, conflicts will occur.

## Lifecycle

| Hook       | Called When          | Use For                         |
|------------|----------------------|---------------------------------|
| `init()`   | First initialization | Setup, event registration       |
| `update()` | After DOM changes    | Re-register events, update refs |

**Note**: Base plugin does NOT implement `destroy()` or automatic cleanup. Implement custom `destroy()` method if needed and call manually.

## Conventions

- **Private methods**: Prefix with underscore (`_methodName`)
- **Element validation**: Check `this.el.nodeName` in `init()` if specific type required
- **Option validation**: Use `console.warn()` or `console.error()` for missing required options instead of throwing errors
- **Event cleanup**: Store bound handlers, remove when plugin is done
- **AJAX**: Use `fetch()` for Store API calls. For App System, use `AppClientService`. `HttpClient` is deprecated (v6.8.0)
- **Loading**: `PageLoadingIndicatorUtil` (global page loader) or `ElementLoadingIndicatorUtil` (element-specific). Both have `.create()` and `.remove()` methods
