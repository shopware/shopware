<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554901441SalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554901441;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `sales_channel_api_context` (
              `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `payload` json NOT NULL,
              PRIMARY KEY (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            DROP TABLE `storefront_api_context`;
        ');
    }
}
