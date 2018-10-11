<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232709CountryState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232709;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `country_state` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `country_id` binary(16) NOT NULL,
              `country_tenant_id` binary(16) NOT NULL,
              `country_version_id` binary(16) NOT NULL,
              `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `active` tinyint(1) NOT NULL DEFAULT \'1\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              CONSTRAINT `fk_area_country_state.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
