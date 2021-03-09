[titleEn]: <>(Core Module List)
[hash]: <>(article:core_modules)

All core modules encapsulate domain concepts and provide a varying number of external interfaces to support this.
The following list provides a rough overview what domain concepts offer what kinds of interfaces.

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

* [User guide](./50-checkout-process/10-cart.md)

#### Customer <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Customer)

SalesChannel Customer

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-customer.md)

#### Document <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Document)

Order document handling

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-document.md)

#### Order <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Order)

Order management

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-order.md)

#### Payment <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Payment)

Payment methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-payment.md)

#### Promotion <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Promotion)

Promotions

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-promotion.md)

#### Shipping <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Checkout/Shipping)

Shipping methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-shipping.md)

### Content Bundle

#### Category <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Category)

Product Categories

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-category.md)

#### Cms <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Cms)

Content Management System

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-cms.md)

#### ContactForm <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ContactForm)

Contact form

* [User guide](./../../45-store-api-guide/40-account.md#contact-form)

#### ImportExport <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ImportExport)

Mass imports and exports through files

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-importexport.md)

#### LandingPage <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/LandingPage)

__EMPTY__

#### Mail <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Mail)

__EMPTY__

#### MailTemplate <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

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

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-newsletter.md)

#### Product <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Product)

Products and Variants

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-product.md)

#### ProductExport <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ProductExport)

Product export

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productexport.md)

#### ProductStream <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/ProductStream)

Product Streams

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productstream.md)

#### Property <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Property)

Content configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-property.md)

#### Rule <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Rule)

Rule Builder

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-rule.md)

#### Seo <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Seo)

Search engine optimization

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-seo.md)

#### Sitemap <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Content/Sitemap)

Sitemap

### Framework Bundle

#### Adapter <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Adapter)

Adapter for external dependencies like Twig and the filesystem

#### Api <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Api)

Rest-API

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-api.md)

#### App <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/App)

Shopware app system

* [User guide](./../../47-app-system-guide/__categoryInfo.md)

#### Changelog <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Changelog)

Console commands for the changelog workflow

#### DataAbstractionLayer <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/DataAbstractionLayer)

Data Abstraction Layer - the central component responsible for all storage access.

* [user guide](./130-dal.md)

#### Event <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Event)

Business events

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-event.md)

#### Feature <span class="tip is--primary">Maintenance</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Feature)

Feature Flag configuration

* [User guide](./../20-administration/40-feature-flag-handling.md)

#### Log <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Log)

Logging

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-log.md)

#### MessageQueue <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/MessageQueue)

Async processing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-messagequeue.md)
* [Guide](./../../20-developer-guide/80-core/10-message-queue.md)

#### Migration <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Migration)

Database migration system

* [User guide](./../../20-developer-guide/70-migrations.md)

#### Parameter 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Parameter)

Global parameters

#### Plugin <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Plugin)

Shopware plugin system

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)

#### Routing <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Routing)

Plugin services

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)

#### Rule <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Rule)

Rule matching

#### Store <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Store)

Plugin store

#### Struct <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Struct)

Structured data

#### Update <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Update)

Update process

#### Uuid 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Uuid)

UUID Handling

#### Validation <span class="tip is--primary">Custom Extendable</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Validation)

Validation

#### Webhook <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Framework/Webhook)

Webhooks

### Migration Bundle

#### Traits 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Migration/Traits)

Traits for different migration operations

#### V6_3 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Migration/V6_3)

__EMPTY__

#### V6_4 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Migration/V6_4)

__EMPTY__

#### V6_5 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/Migration/V6_5)

__EMPTY__

### System Bundle

#### Annotation 

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Annotation)

Annotations which define deprecations and extension possibilities

#### Country <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Country)

Countries

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-country.md)

#### Currency <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span> <span class="tip is--primary">Custom Rules</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Currency)

Currencies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-currency.md)

#### CustomField <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/CustomField)

Custom field management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-customfield.md)

#### DeliveryTime <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/DeliveryTime)

Delivery time

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-deliverytime.md)

#### Integration <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Integration)

Admin integrations

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-integration.md)

#### Language <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Language)

Languages

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-language.md)

#### Locale <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Locale)

Locales

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-locale.md)

#### NumberRange <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/NumberRange)

Number ranges (SKU)

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-numberrange.md)
* [Guide](./../../20-developer-guide/80-core/30-number-range.md)

#### SalesChannel <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/SalesChannel)

Sales Channels

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-saleschannel.md)

#### Salutation <span class="tip is--primary">Data store</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Salutation)

Salutation

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-salutation.md)

#### Snippet <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Snippet)

Translation management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-snippet.md)

#### StateMachine <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom actions</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/StateMachine)

Order state management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-statemachine.md)

#### SystemConfig <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/SystemConfig)

Shopware 6 Configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-systemconfig.md)

#### Tag <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Tag)

Taxonomies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tag.md)

#### Tax <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Tax)

Taxes

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tax.md)

#### Unit <span class="tip is--primary">Data store</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/Unit)

Product / Shipping Units

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-unit.md)

#### User <span class="tip is--primary">Data store</span> <span class="tip is--primary">Maintenance</span> <span class="tip is--primary">Custom Extendable</span> <span class="tip is--primary">Business Event Dispatcher</span> <span class="tip is--primary">Extension</span>

* [Sources](https://github.com/shopware/platform/tree/master/src/Core/System/User)

Admin Users

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-user.md)
