<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationRuntimeTest — fails with a foreign-key-violation message
 * in the shape MigrationRuntime::enrichException() parses.
 *
 * @internal
 */
class Migration1000000003FkFailing extends MigrationStep
{
    final public const ERROR_MESSAGE = 'An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails on TABLE `child_table` FOREIGN KEY REFERENCES `parent_table` (`id`)';

    public function getCreationTimestamp(): int
    {
        return 1000000003;
    }

    public function update(Connection $connection): void
    {
        throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
