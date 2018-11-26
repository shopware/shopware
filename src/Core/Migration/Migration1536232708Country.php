<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232708Country extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232708;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `country` (
              `id` binary(16) NOT NULL,
              `iso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `shipping_free` tinyint(1) NOT NULL DEFAULT \'0\',
              `tax_free` tinyint(1) NOT NULL DEFAULT \'0\',
              `taxfree_for_vat_id` tinyint(1) NOT NULL DEFAULT \'0\',
              `taxfree_vatid_checked` tinyint(1) NOT NULL DEFAULT \'0\',
              `active` tinyint(1) NOT NULL DEFAULT \'1\',
              `iso3` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `display_state_in_registration` tinyint(1) NOT NULL DEFAULT \'0\',
              `force_state_in_registration` tinyint(1) NOT NULL DEFAULT \'0\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
