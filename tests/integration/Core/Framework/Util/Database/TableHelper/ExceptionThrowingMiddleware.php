<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util\Database\TableHelper;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * @internal
 */
class ExceptionThrowingMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new ExceptionThrowingDriver($driver);
    }
}
