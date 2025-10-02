# JavaScript Component System

## Introduction

For Twig components that have to implement interactive funcationality via JavaScript, we introduce a corresponding JavaScript component system, which can be seen as the successor of the former JS plugin system. There are some parts which will seem familiar if you aleady know the plugin system, but some parts were changed and improved.

### Major differences between plugin and component system.

1. **Automatic initialization**  
    If the component is registered properly it will automatically be initialized on the corresponding elements. Even if the DOM tree changes and elements are added or removed, the component will automatically be initiallized on added elements or destroyed for removed elements. No more manual re-initialization of plugins that have to work in conjunction after dynamic DOM changes.

2. **Better events instead of overrides**  
    The current override technique of the plugin system was not reintroduced to the component system, as it showed some major flaws, as overrides could only happen once which can lead to conflicts between different Shopware exntensions. Instead there is a central event system which is easier to use and offers a more robust public interface. In addtion, it offers special interception events, for example, to manupilate request data before it is send.

3. **No imports**  
    We decided to make everything related to the component system available via global scope. This means it is available at the `window` object level and can directly be used in plain JavaScript. No imports or bundling is necessary. You can still use the bundling as it is avialable today or use your own build processes if desired, but the component scripts target for plain JavaScript that don't need to be build in conjuction with our core files.

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

- Manages component registration.
- Handles automatic component discovery and initialization.
- Provides methods for component communication.
- Provices a central event system for cross component communication.

## Creating Components

### Basic Component Structure

The component has to extend the `ShopwareComponent` class, which is globally available.

```javascript
class MyComponent extends ShopwareComponent {

    // Define the CSS selector for automatic initialization.
    static selector = '[data-my-component]';
    
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

window.Shopware.registerComponent('my-component', MyComponent);
```

### Component Registration

Components are registered with the global Shopware instance. You can do this simply in the same file as your component.

```javascript
// Register the component
window.Shopware.registerComponent('my-component', MyComponent);
```

### Automatic Initialization

To define which elements the component should be applied to, you can define a static property `selector` in your component class. The component will then automatically be initialized on elements that match the selector. This also applies to elements that might be added later to the document. You do not need to do this manually.

```javascript
class MyComponent extends ShopwareComponent {

    static selector = '[data-my-component]';
    
    init() {
        console.log('Component automatically initialized!');
    }
}
```

## Component Configuration

### Data Attributes

Components can be configured through data attributes using the pattern `data-{component-name}-options`. For example, you can pass information form Twig into your component. The options should be passed as a JSON string.

```Twig

{% set componentOptions = {
    foo: "bar" 
    test: true
} %}

<div data-my-component 
     data-my-component-options="{{ componentOptions|json_encode }}">
</div>
```

The component name is automatically converted to dash-case for the data attribute. The passed options are merged with the default options that you define as a static property in your component class.

## Directory Structure & Component Script Loading

Component scripts are automatically loaded when the corresponding Twig component is used within the page. Your component script should have the same name as the Twig file of your component and should be placed in the same directory. Shopware will automatically collect all component script files and include them into the page if the component is used on a specific page.

Example structure:

```
views/
  components/
    MyComponentNamespace/
      MyComponent.thml.twig
      MyComopnent.js
      MyComponent.scss
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

<div data-my-component 
     data-my-component-options='{{ jsOptions|json_encode }}'>

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

<div data-my-component 
     data-my-component-options='{{ this.props|json_encode }}'>

    {# Some component logic ... #}
</div>
```


## Component Communication

### Event System

To react to actions from other components, there is a new central event system available which can be accessed via the global `window.Shopware` singleton.

In your component you can emit events to inform others about an action and pass additional data via the event.

```javascript
// MyComponent.js

class MyComponent extends ShopwareComponent {

    // ...

    doSomething() {
        const message = 'Hello World!';

        window.Shopware.emit('MyComponent:DoSomething', message);
    }
}
```

Other components can the subscribe to this event to react on that.

```javascript
// SomeOtherComponent.js

class SomeOtherComponent extends ShopwareComponent {

    init() {
        window.Shopware.on('MyComponent:DoSomething', (message) => {
            this.el.innerText = message;
        });
    }
}
```

Of course, you can also register to events from anywhere else, also from outside of the component system. For example, if you just want to extend the logic of an existing component.

### Event Interception

In addition to the normal asynchronous events, there is a seprate event type which expects a return value that gets further processed within the component. These events make it even easier to extend a components logic and offers a bunch of different use cases, like manipulating request data before it gets send. 

For example the BuyButton component offers an event `BuyButton:PreSubmit` which is interceptable, as it is called via `emitInterception()`. It is triggered when a user clicks the buy button of a product.

```javascript
// BuyButton.js

class BuyButton extends ShopwareComponent {

    // ...

    onFormSubmit(event) {
        event.preventDefault();

        let requestUrl = this.el.getAttribute('action');
        let formData = window.Shopware.serializeForm(this.el);

        ({ requestUrl, formData } = window.Shopware.emitInterception(`${this.componentName}:PreSubmit`, { requestUrl, formData }));

        window.Shopware.emit('BuyButton:Submit', requestUrl, formData);

        window.Shopware.callPluginMethod('OffCanvasCart', 'openOffCanvas', requestUrl, formData);
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
Shopware.callMethod('MyComponent', 'refresh');

// Get all instances of a component
const instances = Shopware.getComponentInstances('MyComponent');

// Get a specific instance by element
const instance = Shopware.getComponentInstanceByElement('MyComponent', element);
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
| `selector` | CSS selector for automatic initialization |
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

### Shopware

#### Methods

| Method | Description |
|--------|-------------|
| `registerComponent(name, component)` | Register a component |
| `unregisterComponent(name)` | Unregister a component |
| `getComponent(name)` | Get a component class |
| `getComponentInstances(name)` | Get all instances of a specific component |
| `getComponentInstanceByElement(name, element)` | Get a component instance of a specific element |
| `emit(eventName, ...args)` | Emit a global event |
| `on(eventName, callback)` | Subscribe to a global event |
| `intercept(eventName, callback, priority)` | Intercept an interception event |
| `emitInterception(eventName, ...args)` | Emit an interceptable event |
| `callMethod(name, methodName, ...args)` | Call a method on all instances of a component |