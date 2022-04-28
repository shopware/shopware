<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\_fixture\bundles\ShopwareBundleWithMigrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    public function update(Connection $connection): void
    {
        // It does not need to be implemented. Just exist.
    }

    public function updateDestructive(Connection $connection): void
    {
        // It does not need to be implemented. Just exist.
    }
}
