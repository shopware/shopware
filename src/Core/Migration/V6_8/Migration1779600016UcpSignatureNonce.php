<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Replay-protection store for inbound RFC 9421 signatures. Each successfully-
 * verified signature is registered as `(sales_channel_id, kid, signature_hash)`
 * with a short TTL; duplicates within the TTL are rejected.
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600016UcpSignatureNonce extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600016;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_signature_nonce` (
                `id`               BINARY(16)   NOT NULL,
                `sales_channel_id` BINARY(16)   NOT NULL,
                `kid`              VARCHAR(190) NOT NULL,
                `signature_hash`   CHAR(64)     NOT NULL,
                `created`          DATETIME(3)  NOT NULL,
                `expires_at`       DATETIME(3)  NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_signature_nonce.sc_kid_sig` (`sales_channel_id`, `kid`, `signature_hash`),
                KEY `idx.ucp_signature_nonce.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
