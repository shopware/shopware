<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554712204AddLegacyPasswordToCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554712204;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `customer`
            ADD COLUMN `legacy_password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            ADD COLUMN `legacy_encoder` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
