<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/src/Docs/**', // Deprecated
        '**/Test/**', // Testing
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**/src/Elasticsearch/Framework/Command/ElasticsearchTestAnalyzerCommand.php', // Why?
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        // temporary to make Bundles internal
        '**/src/Administration/Administration.php',
        '**/src/Storefront/Storefront.php',
        '**/src/Core/Framework/Framework.php',
        '**/src/Core/Checkout/Checkout.php',
        '**/src/Core/Maintenance/Maintenance.php',
        '**/src/Core/DevOps/DevOps.php',
        '**/src/Core/Profiling/Profiling.php',
        '**/src/Core/System/System.php',
        '**/src/Core/Content/Content.php',
        '**/src/Elasticsearch/Elasticsearch.php',
        '**/src/Core/Content/Product/Aggregate/ProductPrice/ProductPriceCollection.php', // temporary to fix wrong inheritance
    ],
    'errors' => [
        'Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Util\\\\ConfigReader#\\$xsdFile', // Can not be inspected through reflection (__DIR__ constant)
        'Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\UnknownMigrationSourceExceptionBase', // Can not be inspected through reflection if() {class Foo {} }
        'Type.+documentation.+for.+property', // Doc type to native type conversions seems to not correctly be detected by the BC checker
        'Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Doctrine\\\\RetryableTransaction::retryable()', // This is a static method so extending this class is not necessary
        'The annotation "LoginRequired" parameter "allowGuest" has been changed on.+from "" to "true"',
        '.+#__construct().+', // Todo make service constructors @internal

        'The return type of Shopware\\\\Core\\\\Framework\\\\Changelog\\\\Command\\\\Changelog(Check|Change|Create)Command#execute\(\) changed from no type to int',
        'Symfony\\\\Component\\\\HttpFoundation\\\\Response::\\$statusTexts',
        'Symfony\\\\Component\\\\HttpKernel\\\\Kernel#\\$bundles',

        // OpenAPI library update
        'The return type of Shopware\\\\Core\\\\Framework\\\\Api\\\\ApiDefinition\\\\Generator\\\\OpenApi\\\\DeactivateValidationAnalysis#validate',
        'OpenApi\\\\Analysis',

        // Conditional feature flag class loading
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\ProductVariationBuilder#build()',
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\AbstractProductMaxPurchaseCalculator#calculate()',
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\AbstractIsNewDetector#isNew()',
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\AbstractProductVariationBuilder#build()',
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\ProductMaxPurchaseCalculator#calculate()',
        'The parameter \\$product of Shopware\\\\Core\\\\Content\\\\Product\\\\IsNewDetector#isNew()',
        'The parameter \\$definition of Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Dbal\\\\Common\\\\IteratorFactory#createIterator()',
        'The return type of Shopware\\\\Core\\\\Framework\\\\Routing\\\\RouteEventSubscriber',
        'The return type of Symfony\\\\Component\\\\Console\\\\Command\\\\Command#configure()',
        'These ancestors of Shopware\\\\Core\\\\Framework\\\\Script\\\\Api\\\\StoreApiCacheKeyHook have been removed',

        'The annotation "RouteScope" has been removed',
        'The annotation "LoginRequired" has been removed',
        'The annotation "ContextTokenRequired" has been removed',
        'The annotation "Acl" has been removed',

        // temporary to fix types in final methods
        'Shopware\\\\Core\\\\Framework\\\\Plugin\\\\KernelPluginLoader\\\\KernelPluginLoader',
        // Should be safe
        'Method Shopware\\\\Storefront\\\\Framework\\\\Cache\\\\ReverseProxy\\\\FastlyReverseProxyGateway\\#\\_\\_destruct\\(\\) was removed'
    ],
];
