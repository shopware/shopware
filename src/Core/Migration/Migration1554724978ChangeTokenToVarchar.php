<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554724978ChangeTokenToVarchar extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554724978;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            TRUNCATE TABLE `storefront_api_context`;
        ');
        $connection->executeUpdate('
            ALTER TABLE `storefront_api_context`
            MODIFY COLUMN `token` VARCHAR(255) NOT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
