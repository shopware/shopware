<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237796ListingSortingTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237796;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `listing_sorting_translation` (
              `listing_sorting_id` binary(16) NOT NULL,
              `listing_sorting_tenant_id` binary(16) NOT NULL,
              `listing_sorting_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`listing_sorting_id`, `listing_sorting_version_id`, `listing_sorting_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `listing_sorting_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `listing_sorting_translation_ibfk_2` FOREIGN KEY (`listing_sorting_id`, `listing_sorting_version_id`, `listing_sorting_tenant_id`) REFERENCES `listing_sorting` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
