<?php declare(strict_types=1);

namespace Shopware\Core;

require __DIR__ . '/TestBootstrapper.php';

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->bootstrap();
