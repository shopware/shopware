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
        '**/src/Core/Framework/App/Source/AbstractTemporaryDirectoryFactory.php', // dropped (not released yet)
        '**/src/Core/Framework/App/Source/TemporaryDirectoryFactory.php', // dropped decorator (not released yet)
    ],
    'errors' => [
        // ProductReviewLoader moved to core, the entire classes is deprecated, can be removed after 6.7.0.0 release
        'Type of property Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ReviewLoaderResult#\\$.+ changed from .+ to having no type',
        'The return type of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ReviewLoaderResult#.+ changed from .+ to .+',
        'Type of property Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#\\$.+ changed from .+ to having no type',
        'The return type of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#.+ changed from .+ to .+',
        'The parameter .+ of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#.+ changed from .+ to .+',

        // Will be typed in Symfony 8 (maybe)
        'Symfony\\\\Component\\\\Console\\\\Command\\\\Command#configure\(\) changed from no type to void',

        'An enum expression .* is not supported in .*', // Can not be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376

        // Criteria is @final so changing from void should be fine
        'The return type of Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Search\\\\Criteria#setTitle\(\) changed from void',

        // Added new optional parameter to those classes
        'Parameter session was added to Method __construct\(\) of class Shopware\\\\Core\\\\System\\\\SalesChannel\\\\Event\\\\SalesChannelContextCreatedEvent',
        'Parameter collectionClass was added to Method __construct\(\) of class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Attribute\\\\Entity',

        // Changed $languageIdChain parameter to $context in TokenQueryBuilder
        'The parameter $languageIdChain of \\\\Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed from array to array|Shopware\\\\Core\\\\Framework\\\\Context',
        'Parameter 3 of Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed name from languageIdChain to context',
        'Parameter context was added to Method build\(\) of class Shopware\\\\Elasticsearch\\\\TokenQueryBuilder',

        // Changed SHOPWARE_FALLBACK_VERSION to comply with latest composer changes, see: https://github.com/composer/composer/commit/1b5b56f234ab52a9dcfc935228d49e2a5e262e39
        'Value of constant Shopware\\\\Core\\\\Kernel::SHOPWARE_FALLBACK_VERSION changed from \'6.6.9999999.9999999-dev\' to \'6.6.9999999-dev\'',

        // The return type was incorrect and led to an error. It is not a breaking change if it's already breaking.
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Order\\\\OrderCollection#getOrderCustomers\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\CustomerCollection to the non-covariant Shopware\\\\Core\\\\Checkout\\\\Order\\\\Aggregate\\\\OrderCustomer\\\\OrderCustomerCollection',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Order\\\\OrderCollection#getOrderCustomers\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\CustomerCollection to Shopware\\\\Core\\\\Checkout\\\\Order\\\\Aggregate\\\\OrderCustomer\\\\OrderCustomerCollection',

        // Added new optional parameter to those classes
        'Parameter cacheDir was added to Method createTwigEnvironment\(\) of class Shopware\\\\Core\\\\Content\\\\Seo\\\\SeoUrlTwigFactory',

        // Version related const values changed for 7.2 update
        'Value of constant Symfony\\\\Component\\\\HttpKernel\\\\Kernel',

        // Class is marked as @final
        'Parameter clearHttp was added to Method clear\(\) of class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Cache\\\\CacheClearer',
    ],
];
