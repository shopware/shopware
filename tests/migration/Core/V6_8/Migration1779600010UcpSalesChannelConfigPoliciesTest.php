<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600000UcpSalesChannelConfig;
use Shopware\Core\Migration\V6_8\Migration1779600010UcpSalesChannelConfigPolicies;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600010UcpSalesChannelConfigPolicies::class)]
class Migration1779600010UcpSalesChannelConfigPoliciesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600010, (new Migration1779600010UcpSalesChannelConfigPolicies())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        // Ensure the base table exists, then strip the two columns this migration adds.
        (new Migration1779600000UcpSalesChannelConfig())->update($this->connection);
        $this->dropColumns();

        static::assertFalse(TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', 'signature_policy'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', 'idempotency_required'));

        $migration = new Migration1779600010UcpSalesChannelConfigPolicies();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', 'signature_policy'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', 'idempotency_required'));

        // Defaults must match the documented production-safe posture.
        $defaults = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `ucp_sales_channel_config`');
        $byName = [];
        foreach ($defaults as $row) {
            $byName[(string) $row['Field']] = $row;
        }

        static::assertSame('strict', (string) $byName['signature_policy']['Default']);
        static::assertSame('1', (string) $byName['idempotency_required']['Default']);
    }

    private function dropColumns(): void
    {
        foreach (['idempotency_required', 'signature_policy'] as $column) {
            if (TableHelper::columnExists($this->connection, 'ucp_sales_channel_config', $column)) {
                $this->connection->executeStatement(\sprintf(
                    'ALTER TABLE `ucp_sales_channel_config` DROP COLUMN `%s`',
                    $column,
                ));
            }
        }
    }
}
