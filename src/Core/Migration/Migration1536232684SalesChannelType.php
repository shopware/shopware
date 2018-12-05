<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232684SalesChannelType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232684;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `sales_channel_type` (
              `id` binary(16) NOT NULL,
              `cover_url` varchar(500) COLLATE utf8mb4_unicode_ci NULL,
              `icon_name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `screenshot_urls` JSON NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `json.screenshot_urls` CHECK (JSON_VALID(`screenshot_urls`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
      ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
