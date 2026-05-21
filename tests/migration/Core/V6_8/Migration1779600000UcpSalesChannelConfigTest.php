<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600000UcpSalesChannelConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600000UcpSalesChannelConfig::class)]
class Migration1779600000UcpSalesChannelConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600000, (new Migration1779600000UcpSalesChannelConfig())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_sales_channel_config'));

        $migration = new Migration1779600000UcpSalesChannelConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_sales_channel_config'));

        foreach ([
            'id',
            'sales_channel_id',
            'active',
            'ucp_version',
            'profile_uri_strategy',
            'custom_profile_uri',
            'enabled_capabilities',
            'enabled_transports',
            'continue_url_template',
            'platform_allowlist',
            'discovery_budget',
            'webhook_url_override',
            'custom_fields',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', $column),
                \sprintf('Column "%s" is missing from ucp_sales_channel_config', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_sales_channel_config', 'uniq.ucp_scc.sales_channel_id'));
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'ucp_sales_channel_config',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        // Children that may FK back into ucp_sales_channel_config rows must
        // not block the DROP TABLE we use to set up a clean test scenario.
        foreach (['ucp_signing_key', 'ucp_negotiation_session', 'ucp_oauth_client'] as $dependent) {
            if (TableHelper::tableExists($this->connection, $dependent)) {
                $this->connection->executeStatement(\sprintf('DELETE FROM `%s`', $dependent));
            }
        }
        if (TableHelper::tableExists($this->connection, 'ucp_sales_channel_config')) {
            $this->connection->executeStatement('DELETE FROM `ucp_sales_channel_config`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_sales_channel_config`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
