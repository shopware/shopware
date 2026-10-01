<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1777362745MigrateAgenticCommercePluginConfig extends MigrationStep
{
    private const PLUGIN_DOMAIN_PREFIX = 'SwagAgenticCommerce.';
    private const CORE_DOMAIN_PREFIX = 'core.';

    public function getCreationTimestamp(): int
    {
        return 1777362745;
    }

    public function update(Connection $connection): void
    {
        // Cannot rely on the (configuration_key, sales_channel_id) UNIQUE index
        // alone: in MySQL/MariaDB NULL never equals NULL, so an INSERT IGNORE
        // does not deduplicate global-scope rows. Explicit NOT EXISTS with the
        // NULL-safe `<=>` operator handles both global and per-sales-channel
        // scopes correctly.
        $connection->executeStatement(
            <<<'SQL'
            INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
            SELECT
                UNHEX(REPLACE(UUID(), '-', '')),
                REPLACE(plugin.configuration_key, :pluginPrefix, :corePrefix),
                plugin.configuration_value,
                plugin.sales_channel_id,
                :createdAt
            FROM system_config plugin
            WHERE plugin.configuration_key LIKE CONCAT(:pluginPrefix, '%')
              AND NOT EXISTS (
                SELECT 1
                FROM system_config core
                WHERE core.configuration_key = REPLACE(plugin.configuration_key, :pluginPrefix, :corePrefix)
                  AND core.sales_channel_id <=> plugin.sales_channel_id
              )
            SQL,
            [
                'pluginPrefix' => self::PLUGIN_DOMAIN_PREFIX,
                'corePrefix' => self::CORE_DOMAIN_PREFIX,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }
}
