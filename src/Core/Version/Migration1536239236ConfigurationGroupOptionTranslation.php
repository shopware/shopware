<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239236ConfigurationGroupOptionTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239236;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `configuration_group_option_translation` (
              `configuration_group_option_id` binary(16) NOT NULL,
              `configuration_group_option_version_id` binary(16) NOT NULL,
              `configuration_group_option_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `configuration_group_option_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `configuration_group_option_translation_ibfk_2` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
