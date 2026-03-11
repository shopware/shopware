<?php declare(strict_types=1);

namespace Shopware\Core;

require __DIR__ . '/TestBootstrapper.php';

$argv = $_SERVER['argv'] ?? [];
$isUnitSuite = false;
foreach ($argv as $i => $arg) {
    if ($arg === '--testsuite' && ($argv[$i + 1] ?? '') === 'unit') {
        $isUnitSuite = true;
        break;
    }
    if ($arg === '--testsuite=unit') {
        $isUnitSuite = true;
        break;
    }
}

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->setEnableCommercial()
    ->setSkipDatabaseSetup($isUnitSuite)
    ->bootstrap();
