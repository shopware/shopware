<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1623732234AddUpdatedAtIndexToCart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1623732234;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD INDEX `idx.cart.updated_at` (`updated_at`)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
