<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232689ListingFacet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232689;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `listing_facet` (
              `id` binary(16) NOT NULL,
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `unique_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `deletable` tinyint(1) unsigned NOT NULL DEFAULT \'1\',
              `position` int(11) NOT NULL DEFAULT \'1\',
              `payload` JSON NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_identifier` (`unique_key`),
              KEY `sorting` (`display_in_categories`,`position`),
              CONSTRAINT `json.payload` CHECK (JSON_VALID(`payload`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
