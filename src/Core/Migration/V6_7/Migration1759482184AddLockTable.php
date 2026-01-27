<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1759482184AddLockTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759482184;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `lock_keys` (
              `key_id` VARCHAR(64) NOT NULL,
              `key_token` VARCHAR(44) NOT NULL,
              `key_expiration` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`key_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
