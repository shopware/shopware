<?php declare(strict_types=1);

return [
    'Checkout/Cart' => <<<'EOD'
Cart processes

* [User guide](./50-checkout-process/10-cart.md)
EOD
    ,
    'Checkout/Customer' => <<<'EOD'
SalesChannel Customer

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-customer.md)
EOD
    ,
    'Checkout/Document' => <<<'EOD'
Order document handling

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-document.md)
EOD
    ,
    'Checkout/Order' => <<<'EOD'
Order management

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-order.md)
EOD
    ,
    'Checkout/Payment' => <<<'EOD'
Payment methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-payment.md)
EOD
    ,
    'Checkout/Promotion' => <<<'EOD'
Promotions

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-promotion.md)
EOD
    ,
    'Checkout/Shipping' => <<<'EOD'
Shipping methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-shipping.md)
EOD
    ,
    'Content/Category' => <<<'EOD'
Product Categories

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-category.md)
EOD
    ,
    'Content/Cms' => <<<'EOD'
Content Management System

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-cms.md)
EOD
    ,
    'Content/ContactForm' => <<<'EOD'
Contact form

* [User guide](./../../45-store-api-guide/40-account.md#contact-form)
EOD
    ,
    'Content/ImportExport' => <<<'EOD'
Mass imports and exports through files

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-importexport.md)
EOD
    ,
    'Content/MailTemplate' => <<<'EOD'
Mailing

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-mailtemplate.md)
EOD
    ,
    'Content/Media' => <<<'EOD'
Media/File management

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-media.md)
EOD
    ,
    'Content/Newsletter' => <<<'EOD'
Newsletter

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-newsletter.md)
EOD
    ,
    'Content/Product' => <<<'EOD'
Products and Variants

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-product.md)
EOD
    ,
    'Content/ProductExport' => <<<'EOD'
Product export

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productexport.md)
EOD
    ,
    'Content/ProductStream' => <<<'EOD'
Product Streams

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productstream.md)
EOD
    ,
    'Content/Property' => <<<'EOD'
Content configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-property.md)
EOD
    ,
    'Content/Rule' => <<<'EOD'
Rule Builder

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-rule.md)
EOD
    ,
    'Content/Seo' => <<<'EOD'
Search engine optimization

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-seo.md)
EOD
    ,
    'Content/Sitemap' => <<<'EOD'
Sitemap
EOD
    ,
    'Framework/Adapter' => <<<'EOD'
Adapter for external dependencies like Twig and the filesystem
EOD
    ,
    'Framework/Api' => <<<'EOD'
Rest-API

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-api.md)
EOD
    ,
    'Framework/App' => <<<'EOD'
Shopware app system

* [User guide](./../../47-app-system-guide/__categoryInfo.md)
EOD
    ,
    'Framework/DataAbstractionLayer' => <<<'EOD'
Data Abstraction Layer - the central component responsible for all storage access.

* [user guide](./130-dal.md)
EOD
    ,
    'Framework/Event' => <<<'EOD'
Business events

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-event.md)
EOD
    ,
    'Framework/Feature' => <<<'EOD'
Feature Flag configuration

* [User guide](./../20-administration/40-feature-flag-handling.md)
EOD
    ,
    'Framework/Log' => <<<'EOD'
Logging

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-log.md)
EOD
    ,
    'Framework/MessageQueue' => <<<'EOD'
Async processing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-messagequeue.md)
* [Guide](./../../20-developer-guide/80-core/10-message-queue.md)
EOD
    ,
    'Framework/Migration' => <<<'EOD'
Database migration system

* [User guide](./../../20-developer-guide/70-migrations.md)
EOD
    ,
    'Framework/Parameter' => <<<'EOD'
Global parameters
EOD
    ,
    'Framework/Plugin' => <<<'EOD'
Shopware plugin system

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)
EOD
    ,
    'Framework/Routing' => <<<'EOD'
Plugin services

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)
EOD
    ,
    'Framework/Rule' => <<<'EOD'
Rule matching
EOD
    ,
    'Framework/Store' => <<<'EOD'
Plugin store
EOD
    ,
    'Framework/Struct' => <<<'EOD'
Structured data
EOD
    ,
    'Framework/Update' => <<<'EOD'
Update process
EOD
    ,
    'Framework/Uuid' => <<<'EOD'
UUID Handling
EOD
    ,
    'Framework/Validation' => <<<'EOD'
Validation
EOD
    ,
    'Framework/Webhook' => <<<'EOD'
Webhooks
EOD
    ,
    'System/Annotation' => <<<'EOD'
Annotations which define deprecations and extension possibilities
EOD
    ,
    'System/Country' => <<<'EOD'
Countries

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-country.md)
EOD
    ,
    'System/Currency' => <<<'EOD'
Currencies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-currency.md)
EOD
    ,
    'System/CustomField' => <<<'EOD'
Custom field management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-customfield.md)
EOD
    ,
    'System/DeliveryTime' => <<<'EOD'
Delivery time

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-deliverytime.md)
EOD
    ,
    'System/Integration' => <<<'EOD'
Admin integrations

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-integration.md)
EOD
    ,
    'System/Language' => <<<'EOD'
Languages

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-language.md)
EOD
    ,
    'System/Locale' => <<<'EOD'
Locales

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-locale.md)
EOD
    ,
    'System/NumberRange' => <<<'EOD'
Number ranges (SKU)

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-numberrange.md)
* [Guide](./../../20-developer-guide/80-core/30-number-range.md)
EOD
    ,
    'System/SalesChannel' => <<<'EOD'
Sales Channels

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-saleschannel.md)
EOD
    ,
    'System/Salutation' => <<<'EOD'
Salutation

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-salutation.md)
EOD
    ,
    'System/Snippet' => <<<'EOD'
Translation management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-snippet.md)
EOD
    ,
    'System/StateMachine' => <<<'EOD'
Order state management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-statemachine.md)
EOD
    ,
    'System/SystemConfig' => <<<'EOD'
Shopware 6 Configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-systemconfig.md)
EOD
    ,
    'System/Tag' => <<<'EOD'
Taxonomies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tag.md)
EOD
    ,
    'System/Tax' => <<<'EOD'
Taxes

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tax.md)
EOD
    ,
    'System/Unit' => <<<'EOD'
Product / Shipping Units

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-unit.md)
EOD
    ,
    'System/User' => <<<'EOD'
Admin Users

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-user.md)
EOD
    ,
    'Framework/Changelog' => <<<'EOD'
Console commands for the changelog workflow
EOD
    ,
    'Migration/Traits' => <<<'EOD'
Traits for different migration operations
EOD
    ,
    'Migration/6.4' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/6.5' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/unreleased' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/v6_4' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Content/LandingPage' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Content/Mail' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/V6_4' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/V6_3' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Migration/V6_5' => <<<'EOD'
__EMPTY__
EOD
    ,
];
