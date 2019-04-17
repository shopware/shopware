<?php declare(strict_types=1);

return [
    'Framework/Routing' => <<<'EOD'
Routing
EOD
    ,
    'Framework/FeatureFlag' => <<<'EOD'
Feature Flag configuration
EOD
    ,
    'Framework/DataAbstractionLayer' => <<<'EOD'
Data Abstraction Layer - the central component responsible for all storage access.

* [user guide](./20-data-abstraction-layer/__categoryInfo.md)
EOD
    ,
    'Framework/Rule' => <<<'EOD'
Rule matching
EOD
    ,
    'Framework/Doctrine' => <<<'EOD'
Doctrine DBAL extension
EOD
    ,
    'Framework/Struct' => <<<'EOD'
Structured data
EOD
    ,
    'Framework/Translation' => <<<'EOD'
Abstract translations
EOD
    ,
    'Framework/Pricing' => <<<'EOD'
Pricing
EOD
    ,
    'Framework/Filesystem' => <<<'EOD'
Filesystem handling
EOD
    ,
    'Framework/Migration' => <<<'EOD'
Database migration system
EOD
    ,
    'Framework/Plugin' => <<<'EOD'
Plugin services

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-plugin.md)
EOD
    ,
    'Framework/ScheduledTask' => <<<'EOD'
Cron jobs

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-scheduledtask.md)
EOD
    ,
    'Framework/Tag' => <<<'EOD'
Taxonomies

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-tag.md)
EOD
    ,
    'Framework/MessageQueue' => <<<'EOD'
Async processing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-messagequeue.md)
EOD
    ,
    'Framework/Search' => <<<'EOD'
Search indexing

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-search.md)
EOD
    ,
    'Framework/Twig' => <<<'EOD'
Template extension
EOD
    ,
    'Framework/Event' => <<<'EOD'
Business events
EOD
    ,
    'Framework/Attribute' => <<<'EOD'
Attribut management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-attribute.md)
EOD
    ,
    'Framework/Validation' => <<<'EOD'
Validation
EOD
    ,
    'Framework/Api' => <<<'EOD'
Rest-API
EOD
    ,
    'Framework/Snippet' => <<<'EOD'
Translation management

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-snippet.md)
EOD
    ,
    'System/SalesChannel' => <<<'EOD'
Sales Channels

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-saleschannel.md)
EOD
    ,
    'System/SystemConfig' => <<<'EOD'
Platform Configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-systemconfig.md)
EOD
    ,
    'System/StateMachine' => <<<'EOD'
Order state management

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-statemachine.md)
EOD
    ,
    'System/Currency' => <<<'EOD'
Currencies

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-currency.md)
EOD
    ,
    'System/Unit' => <<<'EOD'
Product / Shipping Units

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-unit.md)
EOD
    ,
    'System/NumberRange' => <<<'EOD'
Number ranges (SKU)

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-numberrange.md)
EOD
    ,
    'System/Salutation' => <<<'EOD'
Salutation

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-salutation.md)
EOD
    ,
    'System/Integration' => <<<'EOD'
Admin integrations

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-integration.md)
EOD
    ,
    'System/Tax' => <<<'EOD'
Taxes

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-tax.md)
EOD
    ,
    'System/Locale' => <<<'EOD'
Locales

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-locale.md)
EOD
    ,
    'System/User' => <<<'EOD'
Admin Users

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-user.md)
EOD
    ,
    'System/Country' => <<<'EOD'
Countries

* [Entity relationship diagram](./10-erd/erd-shopware-core-system-country.md)
EOD
    ,
    'Content/Category' => <<<'EOD'
Product Categories

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-category.md)
EOD
    ,
    'Content/Rule' => <<<'EOD'
Rule Builder
EOD
    ,
    'Content/Navigation' => <<<'EOD'
Sales Channel Navigation

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-navigation.md)
EOD
    ,
    'Content/Cms' => <<<'EOD'
Content Management System
EOD
    ,
    'Content/Media' => <<<'EOD'
Media/File management

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-media.md)
EOD
    ,
    'Content/ProductStream' => <<<'EOD'
Product Streams

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-productstream.md)
EOD
    ,
    'Content/Product' => <<<'EOD'
Products and Variants

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-product.md)
EOD
    ,
    'Content/Property' => <<<'EOD'
Content configuration

* [Entity relationship diagram](./10-erd/erd-shopware-core-content-property.md)
EOD
    ,
    'Checkout/Payment' => <<<'EOD'
Payment methods

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-payment.md)
EOD
    ,
    'Checkout/Order' => <<<'EOD'
Order management

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-order.md)
EOD
    ,
    'Checkout/DiscountSurcharge' => <<<'EOD'
Discounts and Surcharges

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-discountsurcharge.md)
EOD
    ,
    'Checkout/Shipping' => <<<'EOD'
Shipping methods
EOD
    ,
    'Checkout/Customer' => <<<'EOD'
SalesChannel Customer

* [Entity relationship diagram](./10-erd/erd-shopware-core-checkout-customer.md)
EOD
    ,
    'Checkout/Cart' => <<<'EOD'
Cart processes
EOD
    ,
    'Framework/Store' => <<<'EOD'
Plugin store
EOD
    ,
    'Framework/Context' => <<<'EOD'
Main context
EOD
    ,
    'Content/NewsletterReceiver' => <<<'EOD'
Newsletter
EOD
    ,
    'Framework/Uuid' => <<<'EOD'
UUID Handling
EOD
    ,
    'Framework/Language' => <<<'EOD'
Languages

* [Entity relationship diagram](./10-erd/erd-shopware-core-framework-language.md)
EOD
    ,
];
