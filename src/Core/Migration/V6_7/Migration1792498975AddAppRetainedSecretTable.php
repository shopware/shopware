<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1792498975AddAppRetainedSecretTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792498975;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS app_retained_secret (
                `shop_id` VARCHAR(64) NOT NULL,
                `app_name` VARCHAR(255) NOT NULL,
                `secret` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`shop_id`, `app_name`, `secret`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
