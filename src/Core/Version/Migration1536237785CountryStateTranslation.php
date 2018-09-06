<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237785CountryStateTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237785;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `country_state_translation` (
              `country_state_id` binary(16) NOT NULL,
              `country_state_tenant_id` binary(16) NOT NULL,
              `country_state_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `country_state_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `country_state_translation_ibfk_2` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
