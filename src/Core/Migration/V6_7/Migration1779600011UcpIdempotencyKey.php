<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Stores Idempotency-Key entries so retried UCP write operations return the
 * cached original response instead of executing twice. Per UCP overview.md,
 * non-idempotent operations (cart create, checkout complete, …) MUST honour
 * a client-supplied `Idempotency-Key` header; reusing a key for a *different*
 * request body returns HTTP 409.
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600011UcpIdempotencyKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600011;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_idempotency_key` (
                `id`                   BINARY(16)    NOT NULL,
                `sales_channel_id`     BINARY(16)    NOT NULL,
                `idempotency_key`      VARCHAR(190)  NOT NULL,
                `route_name`           VARCHAR(190)  NOT NULL,
                `request_fingerprint`  VARCHAR(64)   NOT NULL,
                `response_status`      INT           NOT NULL,
                `response_headers`     JSON          NOT NULL,
                `response_body`        MEDIUMTEXT    NOT NULL,
                `created_at`           DATETIME(3)   NOT NULL,
                `expires_at`           DATETIME(3)   NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_idempotency_key.sc_key` (`sales_channel_id`, `idempotency_key`),
                KEY `idx.ucp_idempotency_key.expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
