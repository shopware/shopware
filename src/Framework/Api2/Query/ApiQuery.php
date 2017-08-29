<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Query;

use Doctrine\DBAL\Connection;

abstract class ApiQuery
{
    abstract public function isExecutable(): bool;

    abstract public function execute(Connection $connection): int;
}
