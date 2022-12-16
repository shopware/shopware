<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Shopware\Core\DevOps\StaticAnalyze\Rector\ClassPackageRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/config',
        __DIR__ . '/custom',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

//    // register a single rule
//    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
//
//    // define sets of rules
//    $rectorConfig->sets([
//        LevelSetList::UP_TO_PHP_81
//    ]);
    $rectorConfig->rule(ClassPackageRector::class);
};
