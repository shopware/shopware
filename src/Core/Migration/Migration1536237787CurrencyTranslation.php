<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237787CurrencyTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237787;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `currency_translation` (
              `currency_id` binary(16) NOT NULL,
              `currency_tenant_id` binary(16) NOT NULL,
              `currency_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `currency_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `currency_translation_ibfk_2` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
