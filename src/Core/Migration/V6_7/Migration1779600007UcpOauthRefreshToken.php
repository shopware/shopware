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
class Migration1779600007UcpOauthRefreshToken extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600007;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_oauth_refresh_token` (
                `identifier`             VARCHAR(80)   NOT NULL,
                `access_token_identifier` VARCHAR(80)  NOT NULL,
                `revoked`                TINYINT(1)    NOT NULL DEFAULT 0,
                `expires_at`             DATETIME(3)   NOT NULL,
                `created_at`             DATETIME(3)   NOT NULL,
                PRIMARY KEY (`identifier`),
                KEY `idx.ucp_oauth_refresh_token.access_token` (`access_token_identifier`),
                KEY `idx.ucp_oauth_refresh_token.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
