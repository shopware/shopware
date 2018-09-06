<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232706StorefrontApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232706;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `storefront_api_context` (
              `tenant_id` binary(16) NOT NULL,
              `token` binary(16) NOT NULL,
              `payload` LONGTEXT NOT NULL,
              PRIMARY KEY (`token`, `tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
