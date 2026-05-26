<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779700000AddAgenticDiscoverySalesChannelConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779700000AddAgenticDiscoverySalesChannelConfig::class)]
class Migration1779700000AddAgenticDiscoverySalesChannelConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1779700000,
            (new Migration1779700000AddAgenticDiscoverySalesChannelConfig())->getCreationTimestamp()
        );
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'agentic_discovery_sales_channel_config'));

        $migration = new Migration1779700000AddAgenticDiscoverySalesChannelConfig();
        $migration->update($this->connection);
        // Idempotency: a second update() call must not throw.
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'agentic_discovery_sales_channel_config'));

        foreach ([
            'id',
            'sales_channel_id',
            'active',
            'expose_agents_md',
            'expose_llms_txt',
            'expose_llms_full_txt',
            'expose_agentic_sitemap',
            'custom_intro',
            'custom_agent_rules',
            'custom_sections',
            'custom_fields',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'agentic_discovery_sales_channel_config', $column),
                \sprintf('Column "%s" is missing from agentic_discovery_sales_channel_config', $column),
            );
        }

        static::assertTrue(
            TableHelper::indexExists(
                $this->connection,
                'agentic_discovery_sales_channel_config',
                'uniq.agentic_discovery_scc.sales_channel_id'
            )
        );
        static::assertTrue(TableHelper::foreignKeyExistsByColumns(
            $this->connection,
            'agentic_discovery_sales_channel_config',
            ['sales_channel_id'],
            'sales_channel',
            ['id']
        ));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'agentic_discovery_sales_channel_config')) {
            $this->connection->executeStatement('DELETE FROM `agentic_discovery_sales_channel_config`');
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `agentic_discovery_sales_channel_config`');
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
