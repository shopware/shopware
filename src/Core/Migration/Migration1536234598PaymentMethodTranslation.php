<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234598PaymentMethodTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234598;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `payment_method_translation` (
              `payment_method_id` binary(16) NOT NULL,
              `payment_method_tenant_id` binary(16) NOT NULL,
              `payment_method_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `additional_description` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `payment_method_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `payment_method_translation_ibfk_2` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
