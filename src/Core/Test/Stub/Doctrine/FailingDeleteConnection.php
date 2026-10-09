<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * A real Connection that fails on delete(), to exercise error-handling paths without a partial mock.
 *
 * @internal
 */
#[Package('framework')]
class FailingDeleteConnection extends Connection
{
    /**
     * @param array<string, mixed> $criteria
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     *
     * @return int|numeric-string
     */
    public function delete(string $table, array $criteria = [], array $types = []): int|string
    {
        throw TestExceptionFactory::createException('test');
    }
}
