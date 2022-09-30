<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1664541794AddIndexCart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664541794;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD INDEX `idx.cart.created_at_updated_at` (`created_at`, `updated_at`)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
