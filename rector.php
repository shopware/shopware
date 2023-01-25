<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Shopware\Core\DevOps\StaticAnalyze\Rector\ClassPackageRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');

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
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
        __DIR__ . '/src/Recovery'
    ]);

    $rectorConfig->rule(ClassPackageRector::class);
};
