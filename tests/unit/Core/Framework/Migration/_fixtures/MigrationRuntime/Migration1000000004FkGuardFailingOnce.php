<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationRuntimeTest — the first update() call fails with the
 * MySQL 8.4 FK-guard error (MySQL bug #118151), the retry succeeds. The runtime
 * instantiates migrations itself, so the call count is tracked statically.
 *
 * @internal
 */
class Migration1000000004FkGuardFailingOnce extends MigrationStep
{
    final public const ERROR_MESSAGE = 'An exception occurred while executing a query: SQLSTATE[HY000]: General error: 1553 Cannot drop index \'\': needed in a foreign key constraint';

    public static int $updateCalls = 0;

    public function getCreationTimestamp(): int
    {
        return 1000000004;
    }

    public function update(Connection $connection): void
    {
        ++self::$updateCalls;

        if (self::$updateCalls === 1) {
            throw new \RuntimeException(self::ERROR_MESSAGE);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
