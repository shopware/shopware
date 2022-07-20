<?php

namespace Shopware\Tests\Bench;

require __DIR__ . '/../../../src/Core/TestBootstrapper.php';

use Shopware\Core\TestBootstrapper;

(new TestBootstrapper())
    ->setForceInstall(false)
    ->setPlatformEmbedded(false)
    ->setBypassFinals(false)
    ->bootstrap();
