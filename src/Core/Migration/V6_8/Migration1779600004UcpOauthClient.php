<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1779600004UcpOauthClient extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600004;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_oauth_client` (
                `id`                BINARY(16)    NOT NULL,
                `sales_channel_id`  BINARY(16)    NOT NULL,
                `client_id`         VARCHAR(190)  NOT NULL,
                `name`              VARCHAR(255)  NOT NULL,
                `redirect_uris`     JSON          NOT NULL,
                `is_confidential`   TINYINT(1)    NOT NULL DEFAULT 0,
                `client_secret_hash` VARBINARY(255) NULL,
                `allowed_scopes`    JSON          NOT NULL,
                `platform_profile_uri` TEXT       NULL,
                `created_at`        DATETIME(3)   NOT NULL,
                `updated_at`        DATETIME(3)   NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_oauth_client.sc_client` (`sales_channel_id`, `client_id`),
                CONSTRAINT `json.ucp_oauth_client.redirect_uris` CHECK (JSON_VALID(`redirect_uris`)),
                CONSTRAINT `json.ucp_oauth_client.allowed_scopes` CHECK (JSON_VALID(`allowed_scopes`)),
                CONSTRAINT `fk.ucp_oauth_client.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
