<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765287397AddConsentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765287397;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent_state` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `identifier` BINARY(16) NULL,
                `state` VARCHAR(20) NOT NULL,
                `actor_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.consent_state` (`name`, `identifier`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `consent_log` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `identifier` BINARY(16) NULL,
                `state` VARCHAR(20) NOT NULL,
                `actor_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.consent_log.history` (`name`, `identifier`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
