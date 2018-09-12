<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232690ListingSorting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232690;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `listing_sorting` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `unique_key` varchar(30) NOT NULL,
              `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `position` int(11) NOT NULL DEFAULT \'1\',
              `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              UNIQUE KEY `uniqueKey` (`unique_key`, `tenant_id`),
              KEY `sorting` (`display_in_categories`,`position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
