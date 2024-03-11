<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('data-services')]
class Migration1693300031UsageDataEntityDeletions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1693300031;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `usage_data_entity_deletion` (
                `id` BINARY(16) NOT NULL,
                `entity_ids` JSON NOT NULL,
                `entity_name` VARCHAR(255) NOT NULL,
                `deleted_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
