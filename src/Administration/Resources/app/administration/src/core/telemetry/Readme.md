# Telemetry in the Administration

To understand how people use the Administration we built a telemetry system that allows us to track user interactions.
It extends the global Shopware object by adding a `Telemetry` object.

## Initialization

The telemetry system is initialized as part of the post initialization process of the Administration.
It registers listeners at the Vue Router and the `loginService`. This is mandatory to track page changes as well as login and logout events.

Additionally, it registers a `MutationObserver` on the document body to later add event listeners to DOM elements that are added dynamically.
This allows us to track clicks on buttons that are not present at the time of initialization.

## Telemetry events

While shaping the API we decided to be as close as possible to the Segment API, which is a widely used analytics API.
As a result there are five main types of events that can be tracked:

* `identify` (a user logs in)
* `page_change`
* `user_interaction` (usually called track in the Segment API)
* `programmatic`
* `reset` (a user logs out)

For better differentiation of user interactions and events fired inside event handlers, JavaScript services or workers
we decided to split the generic `track` event into two separate events: `user_interaction` and `programmatic`.

The events and available properties are described in `/src/core/telemetry/types.js`. 

## Data flow

To deliver the events we decided to use the Admin's built-in event bus.
This allows us to define consumers without changing the telemetry system itself.

Consumers can subscribe to the event bus

```js
Shopware.Utils.EventBus.on('telemetry', yourHandler);
```

### Track User Interactions

To make it easier for developers to track user interactions, we implemented a semi-automated tracking system.
This is a learning from a former approach on tracking in Shopware with automated tracking that produced a lot of but not meaningfull data.

We already talked about the `MutationObserver` that is used to react to DOM changes in the Administration.
On DOM changes we run a couple of tests against the added nodes. For Elements that pass a test we will track interactions on them.

Currently, we track the following interactions: 

* Clicks on links
* Clicks on `button` elements that have the `data-analytics-id` attribute
* Any Element that defines the `data-product-analytics` attribute. 

### Change the tracked event

By default, we add click listeners to the elements that pass the tests. If you want to track a different event, you can add the `data-product-analytics-event` attribute to the element and specify the event name.

```js
<mt-button
    data-analytics-id="my_module.my_component.action"
    data-product-analytics-event="mouseover"
>
Some button in the Admin    
</mt-button>
```

### Add event data

You can add additional event data directly in the DOM adding `data-analytics-*` attributes to the element.

```js
<mt-button
    data-analytics-id="my_module.my_component.action"
    data-analytics-additional-data="This is useful"
>
Some button in the Admin    
</mt-button>
```

This properties will be transformed into snake case and sent as part of the event data. 

### Manually dispatch tracking events

To manually track events you can simply call `Shopware.Telemetry.track(eventData)`. The `eventData` object must contain a `eventName` property.
This can be useful if you want to track progress of a long-running process like installing and activating extensions, track Admin-SDK and Admin-API usage and so on.  

## Debugging telemetry events

While developing you may be interested in the events being fired. To enable logging of telemetry events, you can turn on the debug mode in you dev console in the browser.

```js
Shopware.Telemetry.debug = true;
```

## Naming conventions

To make it easier to analyze the data in the future, we have to define some naming conventions for the events and properties.

### Event properties

Event properties must always be written in `snake_case`.
Event properties must be scalar values (string, number, boolean) or string arrays.

Examples:

* `extension_name`
* `sales_channel_id`
* `product_is_new`

### Button IDs

Button IDs should stay close to the related CSS classes and follow this convention:

`<module>.<page>.<button_name>`

Examples:

* `sw_product.detail.product_save`
* `sw_product.detail.product_duplicate`

### Custom events

Custom event names should follow this convention:

`<module>.<page>.<object>_<action>`

Examples:

* `sw_extension.my_extensions_listing.extension_installed`

### General guidelines

Product analytics is used to track user behaviour in the Administration.
The main goal is to understand how users interact with the product and not what consequences they have in the backend.
Therefore, we should focus on tracking user interactions and not technical events that are not directly related to user behaviour.

Of course, it can make sense to track technical events like API calls or long-running processes, but we should be careful not to track too much and end up with a lot of data that is not useful for analysis.