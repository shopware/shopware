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

        // Added new optional parameter to event
        'Parameter session was added to Method __construct\(\) of class Shopware\\\\Core\\\\System\\\\SalesChannel\\\\Event\\\\SalesChannelContextCreatedEvent',

        // Changed $languageIdChain parameter to $context in TokenQueryBuilder
        'The parameter $languageIdChain of \\\\Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed from array to array|Shopware\\\\Core\\\\Framework\\\\Context',
        'Parameter 3 of Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed name from languageIdChain to context',
        'Parameter context was added to Method build\(\) of class Shopware\\\\Elasticsearch\\\\TokenQueryBuilder',
    ],
];
