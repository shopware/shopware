<?php declare(strict_types=1);

use Frosh\Rector\Rule\v68\EntitySearchResultGetEntitiesRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\BooleanAnd\SimplifyEmptyArrayCheckRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\Ternary\TernaryEmptyArrayArrayDimFetchToCoalesceRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml')
    // resolves container->get('<service id>') to the service class during rule analysis, the same
    // way phpstan-symfony does for the phpstan runs; type-based rules cannot see through the
    // container lookup without it
    ->withPHPStanConfigs([__DIR__ . '/rector-phpstan.neon'])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withFileExtensions(['php'])
    // bigger chunks keep the workers busy with actual analysis instead of per-chunk overhead
    ->withParallel(jobSize: 128)
    ->withSkip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',

        '**/vendor/*',
        '**/node_modules/*',
        '**/Resources/*',

        // BC tests deliberately covering the deprecated delegations until their removal
        EntitySearchResultGetEntitiesRector::class => [
            __DIR__ . '/tests/unit/Core/Framework/DataAbstractionLayer/Search/EntitySearchResultTest.php',
            __DIR__ . '/tests/unit/Core/Content/Product/SalesChannel/Listing/ProductListingResultTest.php',
        ],
    ])
    ->withCache(
        cacheDirectory: __DIR__ . '/var/cache/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withRules([
        // Guards against the deprecated collection surface of EntitySearchResult (see #18655)
        EntitySearchResultGetEntitiesRector::class,
        ClassConstantToSelfClassRector::class,
        DisallowedEmptyRuleFixerRector::class,
        CountArrayToEmptyArrayComparisonRector::class,
        SimplifyEmptyArrayCheckRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        StrlenZeroToIdenticalEmptyStringRector::class,
        TernaryEmptyArrayArrayDimFetchToCoalesceRector::class,
    ])
;
