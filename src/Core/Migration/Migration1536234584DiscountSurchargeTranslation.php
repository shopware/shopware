<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234584DiscountSurchargeTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234584;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `discount_surcharge_translation` (
              `discount_surcharge_id` BINARY(16) NOT NULL,
              `discount_surcharge_tenant_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `language_tenant_id` BINARY(16) NOT NULL,
              `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`discount_surcharge_id`, `discount_surcharge_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `discount_surcharge_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `discount_surcharge_translation_ibfk_2` FOREIGN KEY (`discount_surcharge_id`, `discount_surcharge_tenant_id`) REFERENCES `discount_surcharge` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
