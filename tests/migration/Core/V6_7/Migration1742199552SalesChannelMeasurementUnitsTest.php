<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742199552SalesChannelMeasurementUnits;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1742199552SalesChannelMeasurementUnits::class)]
class Migration1742199552SalesChannelMeasurementUnitsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        // Remove the column if it exists to ensure clean test state
        $exists = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column AND TABLE_SCHEMA = DATABASE()',
            ['table' => 'sales_channel', 'column' => 'measurement_units']
        );

        if ((int) $exists > 0) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel` DROP COLUMN `measurement_units`');
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1742199552', (new Migration1742199552SalesChannelMeasurementUnits())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1742199552SalesChannelMeasurementUnits();

        // Check column doesn't exist initially
        $exists = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column AND TABLE_SCHEMA = DATABASE()',
            ['table' => 'sales_channel', 'column' => 'measurement_units']
        );
        static::assertEquals(0, $exists);

        // Run migration
        $migration->update($this->connection);
        $migration->update($this->connection); // Run twice to ensure idempotency

        // Check column now exists
        $exists = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column AND TABLE_SCHEMA = DATABASE()',
            ['table' => 'sales_channel', 'column' => 'measurement_units']
        );
        static::assertEquals(1, $exists);
    }
}
