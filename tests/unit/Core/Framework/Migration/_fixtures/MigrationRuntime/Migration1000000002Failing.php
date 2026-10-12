<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationRuntimeTest — fails with a generic error.
 *
 * @internal
 */
class Migration1000000002Failing extends MigrationStep
{
    final public const ERROR_MESSAGE = 'kaboom';

    public function getCreationTimestamp(): int
    {
        return 1000000002;
    }

    public function update(Connection $connection): void
    {
        throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    public function updateDestructive(Connection $connection): void
    {
        throw new \RuntimeException(self::ERROR_MESSAGE);
    }
}
