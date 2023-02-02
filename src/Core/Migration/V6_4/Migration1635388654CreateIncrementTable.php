<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1635388654CreateIncrementTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1635388654;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `increment` (
                `pool` VARCHAR(255) NOT NULL,
                `cluster` VARCHAR(255) NOT NULL,
                `key` VARCHAR(255) NOT NULL,
                `count` BIGINT unsigned NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`pool`, `cluster`, `key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
