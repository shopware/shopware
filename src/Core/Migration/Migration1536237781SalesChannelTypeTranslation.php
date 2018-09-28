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
              `sales_channel_type_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `manufacturer` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description_long` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`sales_channel_type_id`, `sales_channel_type_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `sales_channel_type_translation_ibfk_1` FOREIGN KEY (`language_id`, `sales_channel_type_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `sales_channel_type_translation_ibfk_2` FOREIGN KEY (`sales_channel_type_id`, `sales_channel_type_tenant_id`) REFERENCES `sales_channel_type` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
