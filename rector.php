<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Shopware\Core\DevOps\StaticAnalyze\Rector\PackageAttributeRule;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/config',
        __DIR__ . '/custom',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->skip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php'
    ]);

    $rectorConfig->rule(PackageAttributeRule::class);
};
