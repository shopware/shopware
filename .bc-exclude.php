<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/WebInstaller/**', // WebInstaller
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/src/Core/DevOps/System/Command/SyncComposerVersionCommand.php', // symfony configure
        '**/src/Core/Framework/Adapter/Asset/AssetInstallCommand.php', // symfony configure
        '**/src/Core/Framework/App/Payment/Payload/Struct/RecurringPayPayload.php', // missed internal
        '**/src/Core/Framework/App/Payment/Payload/Struct/SyncPayPayload.php', // missed internal
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/CashPayment.php', // duplicate class declarations for compatibility reasons
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/DebitPayment.php', // duplicate class declarations for compatibility reasons
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/DefaultPayment.php', // duplicate class declarations for compatibility reasons
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/InvoicePayment.php', // duplicate class declarations for compatibility reasons
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/PrePayment.php', // duplicate class declarations for compatibility reasons
        '**/src/Core/Checkout/Cart/Event/CartChangedEvent.php', // duplicate class declarations for compatibility reasons,
        '**/src/Core/Framework/Changelog/**', // some missed internal
        '**/src/Core/Service/AllServiceInstaller.php', // missed internal (not released yet)
    ],
    'errors' => [
        'Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Util\\\\ConfigReader#\\$xsdFile', // Can not be inspected through reflection (__DIR__ constant)
        'Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\UnknownMigrationSourceExceptionBase', // Can not be inspected through reflection if() {class Foo {} }
        'Unable to compile initializer in method', // Can not be inspected through reflection https://github.com/Roave/BackwardCompatibilityCheck/issues/698
        'Could not locate constant .* while trying to evaluate constant expression', // Can not be inspected through reflection https://github.com/Roave/BackwardCompatibilityCheck/issues/698
        'Value.+of.+constant', // Changing const values in not a BC per se
        // Renaming of arguments
        'Parameter 1 of Shopware\\\\Elasticsearch\\\\Framework\\\\Indexing\\\\IndexerOffset#__construct\(\) changed name from definitions to mappingDefinitions',

        // Property type change from int to float
        'Type of property Shopware\\\\Core\\\\Framework\\\\Rule\\\\Container\\\\DaysSinceRule#$daysPassed changed from int|null to float|null',
        'The parameter $ranking of Shopware\\\\Elasticsearch\\\\Product\\\\SearchFieldConfig\#\_\_construct\(\) changed from int to int|float',
        'The return type of Shopware\\\\Elasticsearch\\\\Product\\\\SearchFieldConfig\#getRanking\(\) changed from int to the non-covariant int|float',
        'The return type of Shopware\\\\Elasticsearch\\\\Product\\\\SearchFieldConfig\#getRanking\(\) changed from int to int|float',

        // added Predis support, can be removed after 6.5.6.0 release
        'Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Cache\\\\RedisConnectionFactory#create',
        'Shopware\\\\Core\\\\Framework\\\\Increment\\\\RedisIncrementer#__construct',
        'Shopware\\\\Core\\\\Framework\\\\App\\\\Payment\\\\Payload\\\\Struct\\\\SyncPayPayload#__construct()',
        'Shopware\\\\Core\\\\Framework\\\\Api\\\\Sync\\\\FkReference#__construct\(\)',

        'Shopware\\\\Core\\\\Framework\\\\Context.*changed from callable.*',

        // Removed boot method from Bundle
        'Shopware\\\\Core\\\\Framework\\\\Bundle#boot',

        // Internal flag added
        'The number of required arguments for Shopware\\\\Core\\\\Framework\\\\Api\\\\ApiDefinition\\\\Generator\\\\StoreApiGenerator#generate\(\) increased from 3 to 4',
        'Shopware\\\\Core\\\\Framework\\\\Api\\\\ApiDefinition\\\\Generator\\\\StoreApiGenerator was marked \"@internal\"',
        'Shopware\\\\Core\\\\Framework\\\\App\\\\Manifest\\\\Xml\\\\Storefront',
        'Shopware\\\\Core\\\\Framework\\\\App\\\\Manifest\\\\Xml\\\\MainModule',

        // Abstract internal class is not understood
        'Shopware\\\\Core\\\\Framework\\\\App\\\\Payment\\\\Response\\\\AbstractResponse',

        // Removed property, which was unintentionally added
        'Property Shopware\\\\Core\\\\Framework\\\\Rule\\\\Container\\\\OrRule#\\$count was removed',

        'Shopware\\\\Core\\\\Content\\\\Product\\\\ProductEntity#setWishlists\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\Aggregate\\\\CustomerWishlist\\\\CustomerWishlistCollection',

        // Will be typed in Symfony 7
        'Symfony\\\\Component\\\\HttpFoundation\\\\ParameterBag#add\(\) changed from no type to void',
        'Symfony\\\\Component\\\\HttpFoundation\\\\ParameterBag#set\(\) changed from no type to void',

        'Shopware\\\\Storefront\\\\Theme\\\\ThemeScripts was marked "@internal"',

        'An enum expression .* is not supported in .*', // Can not be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376

        'League\\\\OAuth2\\\\Server\\\\Entities\\\\Traits\\\\AccessTokenTrait#initJwtConfiguration\(\) changed from no type to void',

        // v6.7.0.0 Changes
        'The number of required arguments for Shopware\\\\Core\\\\Checkout\\\\Order\\\\Event\\\\OrderStateChangeCriteriaEvent#__construct\(\) increased from 2 to 3',
        'The number of required arguments for Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Event\\\\BeforeLineItemQuantityChangedEvent#__construct\(\) increased from 3 to 4',
        'Type of property Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Event\\\\BeforeLineItemQuantityChangedEvent#\\$lineItem changed from having no type to Shopware\\\\Core\\\\Checkout\\\\Cart\\\\LineItem\\\\LineItem',
        'Type of property Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Event\\\\BeforeLineItemQuantityChangedEvent#\\$cart changed from having no type to Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Cart',
        'Type of property Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Event\\\\BeforeLineItemQuantityChangedEvent#\\$salesChannelContext changed from having no type to Shopware\\\\Core\\\\System\\\\SalesChannel\\\\SalesChannelContext',

        'The return type of Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Cache\\\\CacheInvalidator#invalidateExpired\(\) changed from void'
    ],
];
