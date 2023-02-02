<?php declare(strict_types=1);

namespace SwagManualMigrationTest\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration2 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
