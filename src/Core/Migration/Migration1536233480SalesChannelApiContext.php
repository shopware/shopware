<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233480SalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233480;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `sales_channel_api_context` (
              `token` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `payload` JSON NOT NULL,
              PRIMARY KEY (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
