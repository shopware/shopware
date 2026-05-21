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
class Migration1779600003UcpNegotiationSession extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600003;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_negotiation_session` (
                `id`                     BINARY(16)   NOT NULL,
                `sales_channel_id`       BINARY(16)   NOT NULL,
                `platform_profile_uri`   TEXT         NOT NULL,
                `platform_profile_hash`  VARCHAR(64)  NOT NULL,
                `active_capabilities`    JSON         NOT NULL,
                `protocol_version`       VARCHAR(10)  NOT NULL,
                `last_used_at`           DATETIME(3)  NOT NULL,
                `created_at`             DATETIME(3)  NOT NULL,
                `updated_at`             DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_ns.sc_profile` (`sales_channel_id`, `platform_profile_hash`),
                KEY `idx.ucp_ns.last_used_at` (`last_used_at`),
                CONSTRAINT `json.ucp_ns.active_capabilities` CHECK (JSON_VALID(`active_capabilities`)),
                CONSTRAINT `fk.ucp_ns.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
