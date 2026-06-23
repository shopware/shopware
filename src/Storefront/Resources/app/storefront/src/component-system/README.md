# JavaScript Component System

## Introduction

For Twig components that have to implement interactive functionality via JavaScript, we introduce a corresponding JavaScript component system, which can be seen as the successor of the former JS plugin system. There are some parts which will seem familiar if you already know the plugin system, but some parts were changed and improved.

### Major differences between plugin and component system.

1. **Automatic initialization**
    If the component is implemented properly it will automatically be initialized on the corresponding elements. Even if the DOM tree changes and elements are added or removed, the component will automatically be initialized on added elements or destroyed for removed elements. No more manual re-initialization of plugins that have to work in conjunction after dynamic DOM changes.

2. **No registration needed**
    The component system uses native ES module loading that does everything for you, if you follow the conventions. The script will automatically be loaded and initialized on corresponding elements just based on the component's name.

3. **Better events instead of overrides**
    The current override technique of the plugin system was not reintroduced to the component system, as it showed some major flaws, as overrides could only happen once which can lead to conflicts between different Shopware extensions. Instead there is a central event system which is easier to use and offers a more robust public interface. In addition, it offers special interception events, for example, to manipulate request data before it is sent.

## Overview

The JavaScript component system consists of two main parts:

1. **Shopware** - A new global singleton which acts as a central entry point.
2. **ShopwareComponent** - The abstract base class for all components.

## Component Architecture

### Base Component Class

All components extend from the `ShopwareComponent` base class, which provides:

- **Automatic initialization** based on CSS or data attribute selectors.
- **Lifecycle management** with init, destroy, and update methods.
- **Option merging** from data attributes and constructor parameters.
- **Mutation observation** for reactive updates to attributes or child elements.

### Global Shopware Instance

The `Shopware` class acts as a singleton that:

- Manages component loading via ES modules.
- Handles automatic component discovery and initialization.
- Provides methods for component communication.
- Provides a central event system for cross component communication.

## Creating Components

### Basic Component Structure

The component has to extend the `ShopwareComponent` class, which is globally available. The name of the component class does not have to follow a particular pattern, but the name of the script file should have the same name as your Twig component and should be located right beside the template file.

```javascript
export default class MyComponent extends ShopwareComponent {

    // Define default options
    static options = {
        foo: 'bar',
        test: false
    };

    // Component initialization logic
    init() {
        // e.g. registering event listeners.
        this.setupEventListeners();
    }

    // Cleanup logic when component is destroyed
    destroy() {
        // e.g. remove event listeners.
    }

    // Handle content changes
    onContentUpdate(mutationRecord) {}

    // Handle attribute changes
    onAttributeUpdate(mutationRecord) {}

    // Custom methods
    setupEventListeners() {
        this.el.addEventListener('click', this.handleClick.bind(this));
    }

    handleClick(event) {
        // Custom logic
    }
}
```

### Automatic Initialization

Components don't have to be registered manually. If the script file of your component follows the rules of the Twig component directory structure, they are automatically loaded via ES module loading.

Shopware generates an importmap for all components based on the Twig component tag name. On initialization, Shopware will search for all elements with a `data-component` attribute and will try to load the corresponding script file, if necessary. Just make sure to add the data attribute, including the tag name of your Twig component, to the root element of your component.

```Twig
<div data-component="MyComponentNamespace:MyComponent"></div>
```

When the script is loaded, Shopware will automatically initialize the component class on all elements matching the selector. This also applies to elements that might be added later. You do not need to do this manually. Shopware will observe the DOM tree and initialize components also on elements that are dynamically added to the document.

## Build and dev server

Component scripts/styles are built with Vite from `Resources/views/components/` and then loaded via import map at runtime.

From project root:

```bash
# Full storefront build
composer build:js:storefront
```

For local development with live reload:
```bash
composer storefront:dev-server
```

When the dev server is running, Shopware serves component JS/CSS from the Vite server. When it is stopped, Shopware falls back to published production assets.

## Component Configuration

### Data Attributes

Components can be configured through a data attribute named `data-component-options`. For example, you can pass information form Twig into your component. The options should be passed as a JSON string.

```Twig

{% set componentOptions = {
    foo: "bar"
    test: true
} %}

<div data-component="MyComponentNamespace:MyComponent"
     data-component-options="{{ componentOptions|json_encode }}">
</div>
```

The passed options are merged with the default options that you define as a static property in your component class.

## Directory Structure & Component Script Loading

Component scripts are automatically loaded via ES module loading. Your component script should have the same name as the Twig file of your component and should be placed in the same directory. During the component build, Shopware collects component script/style files and generates build artifacts plus an import map reference for runtime loading. Just make sure that an element within your component template has the `data-component` attribute set.

Example structure:

```
views/
  components/
    MyComponentNamespace/
      MyComponent.html.twig
      MyComponent.js
      MyComponent.scss
```

If you are using the index naming of anonymous components, the corresponding style and JS files must follow the same pattern. This naming provides the possibility to use the directory name as the component name.

```
views/
  components/
    MyComponentNamespace/
        MyComponent/
            index.html.twig
            index.js
            index.scss
```

Styles can be either `*.scss` or `*.css` per component. A component may define only one style source (`MyComponent.scss` or `MyComponent.css`, never both).

Both of the above described structures will still result in a component which can be called with:

```Twig
<twig:MyComponentNamespace:MyComponent>
```

## Twig Component Integration

To integrate the script with your corresponding Twig component you have to ensure that the desired element within your component template has the necessary data attributes.

You can build the options individually from your Twig component properties and other data, or use a separate property for the JS options.

```twig
{# views/component/MyComponent.html.twig #}

{% props
    foo = "bar",
    custom = true,
    jsOptions = {},
%}

<div data-component="MyComponentNamespace:MyComponent"
     data-component-options='{{ jsOptions|json_encode }}'>

    {# Some component logic ... #}
</div>
```

If you want to have an even more component-style approach, you can simply pass through the Twig component properties to your JavaScript component.

```twig
{# views/component/MyComponent.html.twig #}

{% props
    foo = "bar",
    custom = true,
%}

<div data-component="MyComponentNamespace:MyComponent"
     data-component-options='{{ this.props|json_encode }}'>

    {# Some component logic ... #}
</div>
```

## Component Communication

### Event System

To react to actions from other components, there is a new central event system available which can be accessed via the global `window.Shopware` singleton.

In your component you can emit events to inform others about an action and pass additional data via the event.

```javascript
// MyComponent.js

export default class MyComponent extends ShopwareComponent {

    // ...

    doSomething() {
        const message = 'Hello World!';

        window.Shopware.emit('MyComponent:DoSomething', message);
    }
}
```

Other components can then subscribe to this event to react on that.

```javascript
// SomeOtherComponent.js

export default class SomeOtherComponent extends ShopwareComponent {

    init() {
        window.Shopware.on('MyComponent:DoSomething', (message) => {
            this.el.innerText = message;
        });
    }
}
```

Of course, you can also register to events from anywhere else, also from outside of the component system. For example, if you just want to extend the logic of an existing component.

### Event Interception

In addition to the normal asynchronous events, there is a separate event type which expects a return value that gets further processed within the component. These events make it even easier to extend a components logic and offers a bunch of different use cases, like manipulating request data before it gets send.

For example the BuyButton component offers an event `BuyButton:PreSubmit` which is interceptable, as it is called via `emitInterception()`. It is triggered when a user clicks the buy button of a product.

```javascript
// BuyButton.js

export default class BuyButton extends ShopwareComponent {

    // ...

    onFormSubmit(event) {
        event.preventDefault();

        let requestUrl = this.el.getAttribute('action');
        let formData = window.Shopware.serializeForm(this.el);

        ({ requestUrl, formData } = window.Shopware.emitInterception('BuyButton:PreSubmit', { requestUrl, formData }));

        window.Shopware.emit('BuyButton:Submit', requestUrl, formData);

        window.PluginManager.callPluginMethod('OffCanvasCart', 'openOffCanvas', requestUrl, formData);
    }
}
```

You can see that the event `BuyButton:PreSubmit` offers the opportunity to manipulate the `formData` before it gets sent. From any other script you can intercept this event and work with the arguments send via the event.

```javascript
// Intercept the buy button event
window.Shopware.intercept('BuyButton:PreSubmit', (data) => {

    data.formData.append('foo', 'bar');

    return data;
});
```

Don't forget to return the data again, so the component logic can work with it.

There can be multiple subscribers to a single event. They will all be executed in the order as they are registered. You can change the order by passing a priority parameter as an optional third option, when registering an event. By default all subscribers have the priority `0`. The higher the priority the earlier the subscriber is called in the chain. Also negative values are possible to move a subscriber further down the chain.

```javascript
// Another interceptor to the buy button event
window.Shopware.intercept('BuyButton:PreSubmit', (data) => {

    data.formData.delete('foo');
    data.formData.append('bar', 'baz')

    return data;
}, -10);
```

### Method Calling

Besides the event system you can also access other component instances directly, or call methods for all active instances of a component.

```javascript
// Call a method on all instances of a component
Shopware.callMethod('MyComponentNamespace:MyComponent', 'refresh');

// Get all instances of a component
const instances = Shopware.getComponentInstances('MyComponentNamespace:MyComponent');

// Get a specific instance by element
const instance = Shopware.getComponentInstanceByElement('MyComponentNamespace:MyComponent', element);
```



### Mutation Observation

Components can observe DOM and attribute changes on their elements and children. The component base class offers an optional mutation observer that can be started separately if needed.

You can call `initializeObserver()` in your component to start the observer and pass the desired observer configuration. If you want to use this, there are two additional lifecycle methods available to react to content and attribute changes.

```javascript
class ReactiveComponent extends ShopwareComponent {
    init() {
        // Enable observation for content and attribute changes
        this.initializeObserver({
            childList: true,
            attributes: true,
            subtree: true
        });
    }

    onContentUpdate(mutationRecord) {
        // Handle content changes
        this.refreshContent();
    }

    onAttributeUpdate(mutationRecord) {
        // Handle attribute changes
        this.updateFromAttributes();
    }
}
```


## API Reference

### ShopwareComponent

#### Static Properties

| Property | Description |
|----------|-------------|
| `options` | Default component options |

#### Instance Properties

| Property | Description |
|----------|-------------|
| `el` | The DOM element the component is attached to |
| `componentName` | The registered name of the component |
| `options` | Merged component options |

#### Methods

| Method | Description |
|--------|-------------|
| `init()` | Override for your custom component initialization |
| `destroy()` | Override for custom component cleanup |
| `onContentUpdate(mutationRecord: MutationRecord)` | React to content changes |
| `onAttributeUpdate(mutationRecord: MutationRecord)` | React to attribute changes |
| `dispatchEvent(eventName: string, detail: Record, options: EventOptions = { cancelable: true, bubbles: true, composed: false })` | Dispatch a custom browser event on the root element of your component. |

### Shopware

#### Methods

| Method | Description |
|--------|-------------|
| `getComponent(name)` | Get a component class |
| `getComponentInstances(name)` | Get all instances of a specific component |
| `getComponentInstanceByElement(name, element)` | Get a component instance of a specific element |
| `emit(eventName, ...args)` | Emit a global event |
| `emitQueued(eventName, ...args)` | Emit a global event, executed at a safe time prior to control returning to the browser's event loop. |
| `on(eventName, callback)` | Subscribe to a global event |
| `intercept(eventName, callback, priority)` | Intercept an interception event |
| `emitInterception(eventName, ...args)` | Emit an interceptable event |
| `callMethod(name, methodName, ...args)` | Call a method on all instances of a component |