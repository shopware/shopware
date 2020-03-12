[titleEn]: <>(Core Module List)
[hash]: <>(article:core_modules)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this. The following list provides a rough overview what domain concepts offer what kinds of interfaces.  

## Possible characteristics

<span class="tip is--primary">Data store</span>
  : These modules are related to database tables and are manageable through the API. Simple CRUD actions will be available.

<span class="tip is--primary">Maintenance</span>
  : Provide commands executable through CLI to trigger maintenance tasks.

<span class="tip is--primary">Custom actions</span>
  : These modules contain more than simple CRUD actions. They provide special actions and services that ease management and additionally check consistency.

<span class="tip is--primary">SalesChannel-API</span>
  : These modules provide logic through a sales channel for the storefront.

<span class="tip is--primary">Custom Extendable</span>
  : These modules contain interfaces, process container tags or provide custom events as extension points.

<span class="tip is--primary">Business Event Dispatcher</span>
  : Provide special events to handle business cases.

<span class="tip is--primary">Extension</span>
  : These modules contain extensions of - usually Framework - interfaces and classes to provide more specific functions for Shopware 6. 

<span class="tip is--primary">Custom Rules</span>
  : Cross-system process to validate workflow decisions. 


## Modules

### Checkout Bundle

#### Cart <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Cart) 

Cart processes

#### Customer <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Customer) 

SalesChannel Customer

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-customer.md)

#### Order <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Order) 

Order management

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-order.md)

#### Payment <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Payment) 

Payment methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-payment.md)

#### Promotion <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Promotion) 

Promotions

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-promotion.md)

#### Shipping <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Shipping) 

Shipping methods

### Content Bundle

#### Category <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Category) 

Product Categories

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-category.md)

#### Cms <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Cms) 

Content Management System

#### DeliveryTime <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/DeliveryTime) 

Delivery time

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-deliverytime.md)

#### ImportExport <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ImportExport) 

Mass imports and exports through files

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-importexport.md)
  

#### MailTemplate <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/MailTemplate) 

Mailing

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-mailtemplate.md)

#### Media <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Media) 

Media/File management

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-media.md)

#### Newsletter <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Newsletter) 

Newsletter

#### Product <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Product) 

Products and Variants

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-product.md)

#### ProductStream <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ProductStream) 

Product Streams

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productstream.md)

#### Property <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Property) 

Content configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-property.md)

#### Rule <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Rule) 

Rule Builder

### Framework Bundle

#### Api <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Api) 

Rest-API

#### Cache 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Cache) 

Cache helpers

#### Console 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Console) 

Console helpers

#### Context <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Context) 

Main context

#### CustomField <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/CustomField) 

Custom field management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-customfield.md)

#### DataAbstractionLayer <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/DataAbstractionLayer) 

Data Abstraction Layer - the central component responsible for all storage access.

* [user guide](./20-data-abstraction-layer/__categoryInfo.md)

#### Doctrine 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Doctrine) 

Doctrine DBAL extension

#### Event <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Event) 

Business events

#### FeatureFlag <span class="tip is--primary">Maintenance</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/FeatureFlag) 

Feature Flag configuration

#### Filesystem <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Filesystem) 

Filesystem handling

#### Language <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Language) 

Languages

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-language.md)

#### Log 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Log) 

Logging

#### MessageQueue <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/MessageQueue) 

Async processing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-messagequeue.md)
* [Guide](./00-module/message-queue.md)

#### Migration <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Migration) 

Database migration system

#### Plugin <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Plugin) 

Plugin services

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)

#### Pricing <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Pricing) 

Pricing

#### Routing <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Routing) 

Routing

#### Rule <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Rule) 

Rule matching

#### ScheduledTask <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/ScheduledTask) 

Cron jobs

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-scheduledtask.md)
* [Guide](./00-module/scheduled-tasks.md)

#### Snippet <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Snippet) 

Translation management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-snippet.md)

#### Store <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Store) 

Plugin store

#### Struct <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Struct) 

Structured data

#### Translation 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Translation) 

Abstract translations

#### Twig 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Twig) 

Template extension

#### Uuid 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Uuid) 

UUID Handling

#### Validation <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Validation) 

Validation

### System Bundle

#### Country <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Country) 

Countries

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-country.md)

#### Currency <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Currency) 

Currencies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-currency.md)

#### Integration <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Integration) 

Admin integrations

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-integration.md)

#### Locale <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Locale) 

Locales

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-locale.md)

#### NumberRange <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/NumberRange) 

Number ranges (SKU)

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-numberrange.md)
* [Guide](./00-module/number-range.md)

#### SalesChannel <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/SalesChannel) 

Sales Channels

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-saleschannel.md)

#### Salutation <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Salutation) 

Salutation

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-salutation.md)

#### StateMachine <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/StateMachine) 

Order state management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-statemachine.md)

#### SystemConfig <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/SystemConfig) 

Shopware 6 Configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-systemconfig.md)

#### Tag <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Tag) 

Content Tagging

#### Tax <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Tax) 

Taxes

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tax.md)

#### Unit <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Unit) 

Product / Shipping Units

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-unit.md)

#### User <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/User) 

Admin Users

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-user.md)
