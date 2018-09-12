<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237797LocaleTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237797;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `locale_translation` (
              `locale_id` binary(16) NOT NULL,
              `locale_tenant_id` binary(16) NOT NULL,
              `locale_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `territory` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `locale_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `locale_translation_ibfk_2` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
