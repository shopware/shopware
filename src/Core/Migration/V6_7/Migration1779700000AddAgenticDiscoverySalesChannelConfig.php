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
class Migration1779700000AddAgenticDiscoverySalesChannelConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779700000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `agentic_discovery_sales_channel_config` (
                `id`                       BINARY(16)   NOT NULL,
                `sales_channel_id`         BINARY(16)   NOT NULL,
                `active`                   TINYINT(1)   NOT NULL DEFAULT 1,
                `expose_agents_md`         TINYINT(1)   NOT NULL DEFAULT 1,
                `expose_llms_txt`          TINYINT(1)   NOT NULL DEFAULT 1,
                `expose_llms_full_txt`     TINYINT(1)   NOT NULL DEFAULT 1,
                `expose_agentic_sitemap`   TINYINT(1)   NOT NULL DEFAULT 1,
                `custom_intro`             TEXT         NULL,
                `custom_agent_rules`       JSON         NULL,
                `custom_sections`          JSON         NULL,
                `custom_fields`            JSON         NULL,
                `created_at`               DATETIME(3)  NOT NULL,
                `updated_at`               DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.agentic_discovery_scc.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `json.agentic_discovery_scc.custom_agent_rules` CHECK (`custom_agent_rules` IS NULL OR JSON_VALID(`custom_agent_rules`)),
                CONSTRAINT `json.agentic_discovery_scc.custom_sections`    CHECK (`custom_sections`    IS NULL OR JSON_VALID(`custom_sections`)),
                CONSTRAINT `fk.agentic_discovery_scc.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
