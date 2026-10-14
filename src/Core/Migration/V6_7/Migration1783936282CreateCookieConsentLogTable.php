<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates `cookie_consent_log`, the pseudonymous, high-volume record of cookie consent decisions
 * of the `database` storage. It is created for every shop and stays empty until that storage is selected.
 *
 * Sales channel and language are referenced by id only (no foreign keys), so consent
 * evidence survives the deletion of a sales channel or language.
 *
 * @internal
 */
#[Package('framework')]
class Migration1783936282CreateCookieConsentLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783936282;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cookie_consent_log` (
                `id` BINARY(16) NOT NULL,
                `consent_id` VARCHAR(64) NOT NULL,
                `consent_action` VARCHAR(32) NOT NULL,
                `group_decisions` JSON NOT NULL,
                `accepted_cookies` JSON NOT NULL,
                `config_hash` VARCHAR(255) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,

                PRIMARY KEY (`id`),
                KEY `idx.cookie_consent_log.consent_id` (`consent_id`),
                KEY `idx.cookie_consent_log.created_at` (`created_at`),

                CONSTRAINT `json.cookie_consent_log.group_decisions` CHECK (JSON_VALID(`group_decisions`)),
                CONSTRAINT `json.cookie_consent_log.accepted_cookies` CHECK (JSON_VALID(`accepted_cookies`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
