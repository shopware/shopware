<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates `cookie_consent_config_snapshot` of the `database` cookie consent log storage: one row per
 * banner configuration, referenced by the `config_hash` of a decision in `cookie_consent_log`.
 * It is created for every shop and stays empty until that storage is selected.
 *
 * @internal
 */
#[Package('framework')]
class Migration1783936283CreateCookieConsentConfigSnapshotTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783936283;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cookie_consent_config_snapshot` (
                `id` BINARY(16) NOT NULL,
                `config_hash` VARCHAR(255) NOT NULL,
                `cookie_groups` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.cookie_consent_config_snapshot.config_hash` (`config_hash`),

                CONSTRAINT `json.cookie_consent_config_snapshot.cookie_groups` CHECK (JSON_VALID(`cookie_groups`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
