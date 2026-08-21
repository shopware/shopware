<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates the storage for server-side cookie consent logging (GDPR Recital 42):
 * - `cookie_consent_log`: anonymous, high-volume audit trail of consent decisions
 * - `cookie_consent_config_version`: low-volume snapshots of the cookie banner configuration
 *
 * Both tables reference sales channel and language by id only (no foreign keys),
 * so consent evidence survives the deletion of a sales channel or language.
 *
 * @internal
 */
#[Package('framework')]
class Migration1783936282CreateCookieConsentLogTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783936282;
    }

    public function update(Connection $connection): void
    {
        // `server_config_hash` always has a matching `cookie_consent_config_version` row, it is the
        // configuration the server held while logging. `rendered_config_hash` is the unverified hash
        // the client reports for the configuration it displayed and is NULL when no configuration was
        // displayed at all, e.g. when the visitor only used the cookie bar buttons.
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cookie_consent_log` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `consent_action` VARCHAR(32) NOT NULL,
                `group_decisions` JSON NOT NULL,
                `accepted_cookies` JSON NOT NULL,
                `server_config_hash` VARCHAR(255) NOT NULL,
                `rendered_config_hash` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),
                KEY `idx.cookie_consent_log.created_at` (`created_at`),
                KEY `idx.cookie_consent_log.server_config_hash` (`server_config_hash`),

                CONSTRAINT `json.cookie_consent_log.group_decisions` CHECK (JSON_VALID(`group_decisions`)),
                CONSTRAINT `json.cookie_consent_log.accepted_cookies` CHECK (JSON_VALID(`accepted_cookies`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cookie_consent_config_version` (
                `id` BINARY(16) NOT NULL,
                `config_hash` VARCHAR(255) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `cookie_groups` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.cookie_consent_config_version.config_hash` (`config_hash`, `sales_channel_id`, `language_id`),

                CONSTRAINT `json.cookie_consent_config_version.cookie_groups` CHECK (JSON_VALID(`cookie_groups`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
