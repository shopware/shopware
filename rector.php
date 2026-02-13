<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanAnd\SimplifyEmptyArrayCheckRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Identical\StrlenZeroToIdenticalEmptyStringRector;
use Rector\CodeQuality\Rector\Ternary\TernaryEmptyArrayArrayDimFetchToCoalesceRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Config\RectorConfig;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

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
    ])
    ->withCache(__DIR__ . '/var/cache/rector')
    ->withRules([
        ClassConstantToSelfClassRector::class,
        DisallowedEmptyRuleFixerRector::class,
        CountArrayToEmptyArrayComparisonRector::class,
        SimplifyEmptyArrayCheckRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        StrlenZeroToIdenticalEmptyStringRector::class,
        TernaryEmptyArrayArrayDimFetchToCoalesceRector::class,
    ])
;
