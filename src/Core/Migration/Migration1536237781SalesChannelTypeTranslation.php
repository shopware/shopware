<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237781SalesChannelTypeTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237781;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `sales_channel_type_translation` (
              `sales_channel_type_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `manufacturer` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description_long` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`sales_channel_type_id`, `language_id`),
              CONSTRAINT `fk.sales_channel_type_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_type_translation.sales_channel_type_id` FOREIGN KEY (`sales_channel_type_id`) REFERENCES `sales_channel_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
