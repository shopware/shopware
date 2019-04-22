<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553787779AddVariantRestrictions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553787779;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product` ADD `variant_restrictions` json NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
