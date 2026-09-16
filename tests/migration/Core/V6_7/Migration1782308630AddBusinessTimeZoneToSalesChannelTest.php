<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1782308630AddBusinessTimeZoneToSalesChannel;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1782308630AddBusinessTimeZoneToSalesChannel::class)]
class Migration1782308630AddBusinessTimeZoneToSalesChannelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsNullableColumnWithoutBackfill(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $salesChannelCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `sales_channel`');
        static::assertGreaterThan(0, $salesChannelCount);

        $migration = new Migration1782308630AddBusinessTimeZoneToSalesChannel();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(SalesChannelDefinition::ENTITY_NAME)
            ->getColumn('business_time_zone');

        static::assertFalse($column->getNotnull());
        static::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `sales_channel` WHERE `business_time_zone` IS NOT NULL')
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1782308630,
            (new Migration1782308630AddBusinessTimeZoneToSalesChannel())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, SalesChannelDefinition::ENTITY_NAME, 'business_time_zone');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `sales_channel` DROP COLUMN `business_time_zone`');
    }
}
