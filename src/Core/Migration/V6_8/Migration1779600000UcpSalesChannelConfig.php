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
class Migration1779600000UcpSalesChannelConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_sales_channel_config` (
                `id`                     BINARY(16)   NOT NULL,
                `sales_channel_id`       BINARY(16)   NOT NULL,
                `active`                 TINYINT(1)   NOT NULL DEFAULT 0,
                `ucp_version`            VARCHAR(10)  NOT NULL,
                `profile_uri_strategy`   VARCHAR(16)  NOT NULL DEFAULT 'domain',
                `custom_profile_uri`     VARCHAR(2048) NULL,
                `enabled_capabilities`   JSON         NOT NULL,
                `enabled_transports`     JSON         NOT NULL,
                `continue_url_template`  VARCHAR(2048) NULL,
                `platform_allowlist`     JSON         NULL,
                `discovery_budget`       JSON         NULL,
                `webhook_url_override`   VARCHAR(2048) NULL,
                `custom_fields`          JSON         NULL,
                `created_at`             DATETIME(3)  NOT NULL,
                `updated_at`             DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_scc.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `json.ucp_scc.enabled_capabilities` CHECK (JSON_VALID(`enabled_capabilities`)),
                CONSTRAINT `json.ucp_scc.enabled_transports`   CHECK (JSON_VALID(`enabled_transports`)),
                CONSTRAINT `json.ucp_scc.platform_allowlist`   CHECK (`platform_allowlist` IS NULL OR JSON_VALID(`platform_allowlist`)),
                CONSTRAINT `json.ucp_scc.discovery_budget`     CHECK (`discovery_budget` IS NULL OR JSON_VALID(`discovery_budget`)),
                CONSTRAINT `fk.ucp_scc.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
