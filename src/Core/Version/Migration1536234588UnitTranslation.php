<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234588UnitTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234588;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `unit_translation` (
              `unit_id` binary(16) NOT NULL,
              `unit_version_id` binary(16) NOT NULL,
              `unit_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`unit_id`,`language_id`, `unit_version_id`, `language_tenant_id`, `unit_tenant_id`),
              CONSTRAINT `unit_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `unit_translation_ibfk_2` FOREIGN KEY (`unit_id`, `unit_version_id`, `unit_tenant_id`) REFERENCES `unit` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
