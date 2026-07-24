<?php declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\BooleanAnd\SimplifyEmptyArrayCheckRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\Ternary\TernaryEmptyArrayArrayDimFetchToCoalesceRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Shopware\Core\DevOps\StaticAnalyze\Rector\EntitySearchResultGetEntitiesRector;

return RectorConfig::configure()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withFileExtensions(['php'])
    ->withSkip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',

        '**/vendor/*',
        '**/node_modules/*',
        '**/Resources/*',

        EntitySearchResultGetEntitiesRector::class => [
            // implements the deprecated methods it would rewrite
            __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/EntitySearchResult.php',
            // tests the deprecated methods on purpose (under DisabledFeatures)
            __DIR__ . '/tests/unit/Core/Framework/DataAbstractionLayer/Search/EntitySearchResultTest.php',
            // ElementDataCollection::add() requires the result wrapper, so the getEntities()
            // delegation cannot be applied mechanically; needs a manual migration with the
            // v6.8 EntitySearchResult changes
            __DIR__ . '/src/Core/Content/Cms/DataResolver/CmsSlotsDataResolver.php',
        ],
    ])
    ->withCache(
        cacheDirectory: __DIR__ . '/var/cache/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withRules([
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
