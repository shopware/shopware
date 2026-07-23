<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationRuntimeTest — every update() call fails with the MySQL
 * 8.4 FK-guard error, using the `<unknown key name>` message variant older
 * MySQL 8.4 patch releases emit for MySQL bug #118151. The runtime instantiates
 * migrations itself, so the call count is tracked statically.
 *
 * @internal
 */
class Migration1000000005FkGuardAlwaysFailing extends MigrationStep
{
    final public const ERROR_MESSAGE = 'An exception occurred while executing a query: SQLSTATE[HY000]: General error: 1553 Cannot drop index \'<unknown key name>\': needed in a foreign key constraint';

    public static int $updateCalls = 0;

    public function getCreationTimestamp(): int
    {
        return 1000000005;
    }

    public function update(Connection $connection): void
    {
        ++self::$updateCalls;

        throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
