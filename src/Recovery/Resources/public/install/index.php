<?php declare(strict_types=1);

$rootDir = dirname(__DIR__, 3);

if (file_exists($rootDir . '/vendor/shopware/recovery/Install/index.php')) {
    require_once $rootDir . '/vendor/shopware/recovery/Install/index.php';
} elseif (file_exists($rootDir . '/vendor/shopware/platform/src/Recovery/Install/index.php')) {
    require_once $rootDir . '/vendor/shopware/platform/src/Recovery/Install/index.php';
} elseif (file_exists($rootDir . '/src/Recovery/Install/index.php')) {
    require_once $rootDir . '/src/Recovery/Install/index.php';
}
