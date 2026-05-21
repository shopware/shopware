<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Adds OAuth confidential-client authentication metadata:
 *   - `ucp_oauth_client.jwks_json`                 — pinned JWKS for
 *                                                    `private_key_jwt`
 *   - `ucp_oauth_client.tls_client_auth_subject_dn`— registered DN for
 *                                                    `tls_client_auth`
 *   - `ucp_oauth_client_assertion`                 — jti replay-cache
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600013UcpOAuthClientAuth extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600013;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `ucp_oauth_client`');
        $names = array_column($columns, 'Field');

        // ALTER TABLE .. AFTER is disallowed in migrations to avoid implicit
        // temporary tables on large customer datasets; column position has no
        // functional impact here.
        if (!\in_array('jwks_json', $names, true)) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `ucp_oauth_client`
                ADD COLUMN `jwks_json` JSON NULL
            SQL);
        }
        if (!\in_array('tls_client_auth_subject_dn', $names, true)) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `ucp_oauth_client`
                ADD COLUMN `tls_client_auth_subject_dn` VARCHAR(255) NULL
            SQL);
        }

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_oauth_client_assertion` (
                `id`                BINARY(16)    NOT NULL,
                `sales_channel_id`  BINARY(16)    NOT NULL,
                `iss`               VARCHAR(190)  NOT NULL,
                `jti`               VARCHAR(190)  NOT NULL,
                `expires_at`        DATETIME(3)   NOT NULL,
                `created_at`        DATETIME(3)   NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_oauth_client_assertion.sc_iss_jti` (`sales_channel_id`, `iss`, `jti`),
                KEY `idx.ucp_oauth_client_assertion.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
