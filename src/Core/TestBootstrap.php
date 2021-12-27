<?php declare(strict_types=1);

namespace Shopware\Core;

use Symfony\Component\ErrorHandler\Debug;

require __DIR__ . '/TestBootstrapper.php';

Debug::enable();

(new TestBootstrapper())
    ->setPlatformEmbedded(false)
    ->bootstrap();
