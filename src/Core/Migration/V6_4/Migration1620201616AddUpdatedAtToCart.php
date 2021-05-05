<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1620201616AddUpdatedAtToCart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620201616;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `cart` ADD COLUMN `updated_at` DATETIME(3) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
