<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class MySQLEnumTypeMappingMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new MySQLEnumTypeMappingDriver($driver);
    }
}
