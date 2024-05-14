<?php declare(strict_types=1);

namespace Shopware\Tests\Bench;

require __DIR__ . '/../../../src/Core/TestBootstrapper.php';

use Shopware\Core\TestBootstrapper;

(new TestBootstrapper())
    ->setForceInstall(false)
    ->setPlatformEmbedded(false)
    ->bootstrap();
