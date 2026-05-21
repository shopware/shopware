<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1779600005UcpOauthAuthCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_oauth_auth_code` (
                `identifier`        VARCHAR(80)   NOT NULL,
                `sales_channel_id`  BINARY(16)    NOT NULL,
                `client_id`         VARCHAR(190)  NOT NULL,
                `user_identifier`   VARCHAR(190)  NOT NULL,
                `scopes`            JSON          NOT NULL,
                `redirect_uri`      TEXT          NOT NULL,
                `code_challenge`    VARCHAR(255)  NULL,
                `code_challenge_method` VARCHAR(16) NULL,
                `revoked`           TINYINT(1)    NOT NULL DEFAULT 0,
                `expires_at`        DATETIME(3)   NOT NULL,
                `created_at`        DATETIME(3)   NOT NULL,
                PRIMARY KEY (`identifier`),
                KEY `idx.ucp_oauth_auth_code.expires_at` (`expires_at`),
                CONSTRAINT `json.ucp_oauth_auth_code.scopes` CHECK (JSON_VALID(`scopes`)),
                CONSTRAINT `fk.ucp_oauth_auth_code.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
