<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Stores short-lived sessions for the UCP Embedded Protocol transport.
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600015UcpEmbeddedSession extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600015;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_embedded_session` (
                `id`                  BINARY(16)    NOT NULL,
                `session_token_hash`  CHAR(64)      NOT NULL,
                `sales_channel_id`    BINARY(16)    NOT NULL,
                `cart_id`             VARCHAR(190)  NOT NULL,
                `host_origin`         VARCHAR(255)  NOT NULL,
                `kind`                VARCHAR(16)   NOT NULL,
                `created_at`          DATETIME(3)   NOT NULL,
                `expires_at`          DATETIME(3)   NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_embedded_session.token_hash` (`session_token_hash`),
                KEY `idx.ucp_embedded_session.cart_id` (`cart_id`),
                KEY `idx.ucp_embedded_session.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
