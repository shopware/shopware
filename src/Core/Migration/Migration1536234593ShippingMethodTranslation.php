<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234593ShippingMethodTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234593;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `shipping_method_translation` (
              `shipping_method_id` binary(16) NOT NULL,
              `shipping_method_version_id` binary(16) NOT NULL,
              `shipping_method_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` mediumtext COLLATE utf8mb4_unicode_ci,
              `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `shipping_method_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `shipping_method_translation_ibfk_2` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
