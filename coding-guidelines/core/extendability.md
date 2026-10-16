# Extendability

The Extendability of our software and its features is an essential part of development.
Enabling external companies, but also ourselves, to customize our software so that it can be adapted to different business cases is the foundation for the success of our software.

Regarding software extendability, different business cases and requirements must be considered, according to which we must also build the architecture of the software.

The requirements are divided into technical requirements and business requirements.

## Technical requirements

When talking about technical requirements, we talk about how we have to design our software for different extension use cases.
These use cases include:

* Functional extensibility
  * It should be possible to extend the feature with additional features.
  * Ex: Extend the enterprise search by a suggestion feature
* Functional modifiability
  * You can rewrite the feature in certain areas to modify the functionality.
  * Ex: Implementing tax calculation for America via tax providers
* Functional differentiation
  * The feature should be able to be extended in such a way that certain parts of the software are liable to pay costs.
  * Ex: With the XXX software version, you should be able to unlock another feature.
* Functional exchange market
  * The feature is to be replaced entirely by an external solution
  * Ex: An external newsletter system should be connected

## Business requirements

When talking about business requirements, we talk about how the above technical requirements are used in different business cases.
These business cases include:
* Marketplace extensions
  * We should build the software so everyone can easily provide new features in certain areas.
  * Ex: There should be a plugin to integrate a CMS Page publishing system
* Adaptive technologies
  * We must build certain technical areas so flexibly that we can use different technologies for this area.
  * Ex: Our listings should be able to be read via Elasticsearch for large systems.
* Environment specifications
  * We must program features so that they can resist different loads depending on the setup.
  * Ex: Assets should be able to be loaded via CDN as there are several App Servers.

## Approaches

These business cases are realized with the following three conceptual approaches:
- Project templates
  - Large customers have their deployments in which they deploy a fork of our production template.
  - In project templates, local customizations are not implemented as a plugin but as a bundle.
  - We have a SaaS product that has special configurations for cloud compatibility
- Apps
  - Apps provide minor extensions for our system
  - The app technology is designed for use in cloud products
- Plugins
  - Plugins can provide larger extensions to the system
  - Plugin technology is designed to replace all areas in Shopware

## Patterns

All the above requirements and approaches are based on different design patterns within our architecture.
To realize the extensibility, we use the following patterns, which allow the third-party developer to extend our software:

* Decoration
* Factory
* Visitor
* Mediator
* Adapter

### Decoration

With the Decoration pattern, we make it possible to replace or extend certain areas in Shopware completely.
A use case is the **functional replacement market** case, where we can completely replace features with other technologies or external libraries.

### Factory

The factory pattern is often used when we have to interpret user input and validate or enrich this input before it is passed to the application.
One use case for the factory pattern is the **Functional extensibility**, to allow third-party developers to add new factories, which allow other user input.

A good example is the [line item factory registry](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Checkout/Cart/LineItemFactoryRegistry.php).
This registry is used when an item is to be added to the shopping cart via store-API.
[The corresponding handler](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Checkout/Cart/LineItemFactoryHandler/ProductLineItemFactory.php) is responsible for the instantiation of the line item and enriches it with necessary data.

### Visitor

The visitor pattern is often used when we process some objects within our application.
This pattern is often used to fit the **Functional extensibility** and **Functional modifiability** requirements.
In theory, after or before the core visitors are executed, the third party visitors are executed, and they can visit the objects and manipulate or extend the processed data beforehand or afterward to manipulate the result.

A good example of the visitor pattern is the [cart processor](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Checkout/Cart/Processor.php).
The processor calls all line item processors, like the [product cart process](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Content/Product/Cart/ProductCartProcessor.php),
to modify the provided cart object and transport the line items from the previous cart to the calculated.

### Mediator

The Mediator pattern coordinates interactions through a central object, so participating components do not need to reference each other directly.
We often use this pattern to realize **functional extensibility** and **functional modifiability** to manipulate data or extend it with additional data sources.
Our event system combines this with the Observer pattern through Symfony's [EventDispatcher](https://symfony.com/doc/current/event_dispatcher.html),
allowing listeners to react to events without coupling them to the caller.
We use events to create different entry points for developers to trigger specific processes.

The best-known example is the [`checkout.order.placed`](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Checkout/Cart/Event/CheckoutOrderPlacedEvent.php) event.
This event is [dispatched](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Checkout/Cart/SalesChannel/CartOrderRoute.php) as soon as an order is created in the system.
However, over time, it has been shown that it is best practice not to pass objects or entities around in events, but only a corresponding primary key so that the connected listeners can determine the data for themselves.
Furthermore, possible asynchronous processing of the underlying processes is easier to realize this way.
An optimized variant of this event would not contain the `private OrderEntity $order;` but only the primary key for the order `private string $orderId;`.

#### Extension events

The [ExtensionDispatcher](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Framework/Extensions/ExtensionDispatcher.php) lets plugins modify inputs and results or replace an operation through a typed extension event.

For example, [ProductListingLoader](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Content/Product/SalesChannel/Listing/ProductListingLoader.php) publishes [ResolveListingExtension](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Core/Content/Product/Extension/ResolveListingExtension.php) around product listing loading.
A plugin can adjust the listing criteria, enrich the result, or replace loading entirely.

#### Hooks

Hooks are another good example of the observer pattern.
Hooks are entry points for apps in which the so-called [**App scripts**](https://developer.shopware.com/docs/guides/plugins/apps/app-scripts/) are enabled.
Since apps do not have the permission to execute code on the server directly, hooks are a way to execute more complex business logic within the request without having to address the own app server via HTTP.
Hooks are the equivalent of **events**.

One of the best-known hooks is the [`product page loaded hook`](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Storefront/Page/Product/ProductPageLoadedHook.php).
This hook allows apps to load additional data on the product detail page.
The hook is instantiated and dispatched [at controller level](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Storefront/Controller/ProductController.php).
Each app script, which is registered to the hook, is executed.

### Adapter

The adapter pattern is perfectly designed for **Functional exchange market**.
We often realize this by allowing the user to do some configuration and select a corresponding adapter.
These adapters are usually registered inside a registry, and third-party developers can easily add new adapters via events or via tagged services.

A good example is the captcha implementation.
The store owner can configure a [captcha type](https://docs.shopware.com/en/shopware-en/settings/basic-information#captcha),
and we then use the [corresponding adapter](https://github.com/shopware/shopware/blob/v6.7.14.0/src/Storefront/Framework/Captcha/HoneypotCaptcha.php) in the code for the configured captcha.
