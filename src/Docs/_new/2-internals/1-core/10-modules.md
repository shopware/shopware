[titleEn]: <>(Core Module List)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this. The following list provides a rough overview what domain concepts offer what kinds of interfaces.  

## Possible characteristics

 **Data store**
  : These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.
  
**Maintenance**
  : Provide commands executable through CLI to trigger maintenance tasks.
  
**Custom actions**
  : These modules contain more then simple CRUD actions. They provide special actions and services that ease management and additionally check consistency.
  
**SalesChannel-API**
 : These modules provide logic through a sales channel for the storefront.
 
**Custom Extendable**
 : These modules contain interfaces, process container tags or provide custom events as extension points.
  
**Rule Provider**
  : Cross-system process to validate workflow decisions. 
  
**Business Event Dispatcher**
  : Provide special events to handle business cases.
 
**Extension**
  : These modules contain extensions of - usually Framework - interfaces and classes to provide more specific functions for the Platform. 

### [Framework/Routing](https://github.com/shopware/platform/tree/master/src/Core/Framework/Routing) 
*[Custom Extendable]*

Routing

### [Framework/FeatureFlag](https://github.com/shopware/platform/tree/master/src/Core/Framework/FeatureFlag) 
*[Maintenance]*

Feature Flag configuration

### [Framework/Uuid](https://github.com/shopware/platform/tree/master/src/Core/Framework/Uuid) 


UUID Handling

### [Framework/DataAbstractionLayer](https://github.com/shopware/platform/tree/master/src/Core/Framework/DataAbstractionLayer) 
*[Data store]*, *[Maintenance]*, *[Custom Extendable]*, *[Extension]*

Data Abstraction Layer - the central component responsible for all storage access.

* [user guide](./20-data-abstraction-layer/__categoryInfo.md)

### [Framework/Rule](https://github.com/shopware/platform/tree/master/src/Core/Framework/Rule) 
*[Custom Extendable]*, *[Rule Provider]*, *[Extension]*

Rule matching

### [Framework/Doctrine](https://github.com/shopware/platform/tree/master/src/Core/Framework/Doctrine) 


Doctrine DBAL extension

### [Framework/Struct](https://github.com/shopware/platform/tree/master/src/Core/Framework/Struct) 
*[Custom Extendable]*, *[Extension]*

Structured data

### [Framework/Translation](https://github.com/shopware/platform/tree/master/src/Core/Framework/Translation) 


Abstract translations

### [Framework/Pricing](https://github.com/shopware/platform/tree/master/src/Core/Framework/Pricing) 
*[Extension]*

Pricing

### [Framework/Filesystem](https://github.com/shopware/platform/tree/master/src/Core/Framework/Filesystem) 
*[Custom Extendable]*

Filesystem handling

### [Framework/Migration](https://github.com/shopware/platform/tree/master/src/Core/Framework/Migration) 
*[Maintenance]*, *[Custom Extendable]*

Database migration system

### [Framework/Plugin](https://github.com/shopware/platform/tree/master/src/Core/Framework/Plugin) 
*[Data store]*, *[Maintenance]*, *[Custom Extendable]*, *[Extension]*

Plugin services

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)

### [Framework/Language](https://github.com/shopware/platform/tree/master/src/Core/Framework/Language) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Languages

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-language.md)

### [Framework/ScheduledTask](https://github.com/shopware/platform/tree/master/src/Core/Framework/ScheduledTask) 
*[Data store]*, *[Maintenance]*, *[Custom Extendable]*, *[Extension]*

Cron jobs

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-scheduledtask.md)

### [Framework/Tag](https://github.com/shopware/platform/tree/master/src/Core/Framework/Tag) 
*[Data store]*, *[Extension]*

Taxonomies

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-tag.md)

### [Framework/MessageQueue](https://github.com/shopware/platform/tree/master/src/Core/Framework/MessageQueue) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Async processing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-messagequeue.md)

### [Framework/Search](https://github.com/shopware/platform/tree/master/src/Core/Framework/Search) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Search indexing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-search.md)

### [Framework/Twig](https://github.com/shopware/platform/tree/master/src/Core/Framework/Twig) 


Template extension

### [Framework/Event](https://github.com/shopware/platform/tree/master/src/Core/Framework/Event) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Business events

### [Framework/Context](https://github.com/shopware/platform/tree/master/src/Core/Framework/Context) 
*[Custom Extendable]*

Main context

### [Framework/Attribute](https://github.com/shopware/platform/tree/master/src/Core/Framework/Attribute) 
*[Data store]*, *[Custom actions]*, *[Custom Extendable]*, *[Extension]*

Attribut management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-attribute.md)

### [Framework/Validation](https://github.com/shopware/platform/tree/master/src/Core/Framework/Validation) 
*[Custom Extendable]*

Validation

### [Framework/Api](https://github.com/shopware/platform/tree/master/src/Core/Framework/Api) 
*[Maintenance]*, *[SalesChannel-API]*, *[Custom Extendable]*

Rest-API

### [Framework/Store](https://github.com/shopware/platform/tree/master/src/Core/Framework/Store) 
*[Data store]*, *[Extension]*

Plugin store

### [Framework/Snippet](https://github.com/shopware/platform/tree/master/src/Core/Framework/Snippet) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Translation management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-snippet.md)

### [System/SalesChannel](https://github.com/shopware/platform/tree/master/src/Core/System/SalesChannel) 
*[Data store]*, *[Maintenance]*, *[SalesChannel-API]*, *[Custom Extendable]*, *[Extension]*

Sales Channels

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-saleschannel.md)

### [System/SystemConfig](https://github.com/shopware/platform/tree/master/src/Core/System/SystemConfig) 
*[Data store]*, *[Extension]*

Platform Configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-systemconfig.md)

### [System/StateMachine](https://github.com/shopware/platform/tree/master/src/Core/System/StateMachine) 
*[Data store]*, *[Maintenance]*, *[Extension]*

Order state management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-statemachine.md)

### [System/Currency](https://github.com/shopware/platform/tree/master/src/Core/System/Currency) 
*[Data store]*, *[Rule Provider]*, *[Extension]*

Currencies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-currency.md)

### [System/Unit](https://github.com/shopware/platform/tree/master/src/Core/System/Unit) 
*[Data store]*, *[Extension]*

Product / Shipping Units

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-unit.md)

### [System/NumberRange](https://github.com/shopware/platform/tree/master/src/Core/System/NumberRange) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Number ranges (SKU)

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-numberrange.md)

### [System/Salutation](https://github.com/shopware/platform/tree/master/src/Core/System/Salutation) 
*[Data store]*, *[Extension]*

Salutation

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-salutation.md)

### [System/Integration](https://github.com/shopware/platform/tree/master/src/Core/System/Integration) 
*[Data store]*, *[Extension]*

Admin integrations

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-integration.md)

### [System/Tax](https://github.com/shopware/platform/tree/master/src/Core/System/Tax) 
*[Data store]*, *[Extension]*

Taxes

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tax.md)

### [System/Locale](https://github.com/shopware/platform/tree/master/src/Core/System/Locale) 
*[Data store]*, *[Extension]*

Locales

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-locale.md)

### [System/User](https://github.com/shopware/platform/tree/master/src/Core/System/User) 
*[Data store]*, *[Maintenance]*, *[Extension]*

Admin Users

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-user.md)

### [System/Country](https://github.com/shopware/platform/tree/master/src/Core/System/Country) 
*[Data store]*, *[Extension]*

Countries

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-country.md)

### [Content/Category](https://github.com/shopware/platform/tree/master/src/Core/Content/Category) 
*[Data store]*, *[SalesChannel-API]*, *[Extension]*

Product Categories

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-category.md)

### [Content/NewsletterReceiver](https://github.com/shopware/platform/tree/master/src/Core/Content/NewsletterReceiver) 
*[Data store]*, *[Extension]*

Newsletter

### [Content/Rule](https://github.com/shopware/platform/tree/master/src/Core/Content/Rule) 
*[Data store]*, *[Extension]*

Rule Builder

### [Content/Navigation](https://github.com/shopware/platform/tree/master/src/Core/Content/Navigation) 
*[Data store]*, *[Extension]*

Sales Channel Navigation

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-navigation.md)

### [Content/Property](https://github.com/shopware/platform/tree/master/src/Core/Content/Property) 
*[Data store]*, *[Extension]*

Content configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-property.md)

### [Content/Cms](https://github.com/shopware/platform/tree/master/src/Core/Content/Cms) 
*[Data store]*, *[Maintenance]*, *[SalesChannel-API]*, *[Custom Extendable]*, *[Extension]*

Content Management System

### [Content/Media](https://github.com/shopware/platform/tree/master/src/Core/Content/Media) 
*[Data store]*, *[Maintenance]*, *[Custom Extendable]*, *[Extension]*

Media/File management

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-media.md)

### [Content/ProductStream](https://github.com/shopware/platform/tree/master/src/Core/Content/ProductStream) 
*[Data store]*, *[Extension]*

Product Streams

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productstream.md)

### [Content/Product](https://github.com/shopware/platform/tree/master/src/Core/Content/Product) 
*[Data store]*, *[Custom actions]*, *[SalesChannel-API]*, *[Custom Extendable]*, *[Extension]*

Products and Variants

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-product.md)

### [Checkout/Payment](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Payment) 
*[Data store]*, *[Custom Extendable]*, *[Extension]*

Payment methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-payment.md)

### [Checkout/Order](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Order) 
*[Data store]*, *[Custom actions]*, *[Extension]*

Order management

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-order.md)

### [Checkout/DiscountSurcharge](https://github.com/shopware/platform/tree/master/src/Core/Checkout/DiscountSurcharge) 
*[Data store]*, *[Extension]*

Discounts and Surcharges

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-discountsurcharge.md)

### [Checkout/Shipping](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Shipping) 
*[Data store]*, *[Extension]*

Shipping methods

### [Checkout/Customer](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Customer) 
*[Data store]*, *[SalesChannel-API]*, *[Custom Extendable]*, *[Rule Provider]*, *[Extension]*

SalesChannel Customer

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-customer.md)

### [Checkout/Cart](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Cart) 
*[Custom actions]*, *[SalesChannel-API]*, *[Custom Extendable]*, *[Rule Provider]*, *[Extension]*

Cart processes
