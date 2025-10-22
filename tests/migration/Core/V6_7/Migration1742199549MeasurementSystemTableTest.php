<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742199549MeasurementSystemTable;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1742199549MeasurementSystemTable::class)]
class Migration1742199549MeasurementSystemTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1742199549', (new Migration1742199549MeasurementSystemTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $sm = $this->connection->createSchemaManager();

        static::assertFalse($sm->tablesExist(['measurement_system']));
        static::assertFalse($sm->tablesExist(['measurement_system_translation']));

        $migration = new Migration1742199549MeasurementSystemTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['measurement_system']));
        static::assertTrue($sm->tablesExist(['measurement_system_translation']));

        // Check that default systems were created
        $metricCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `measurement_system` WHERE `technical_name` = "metric"');
        $imperialCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `measurement_system` WHERE `technical_name` = "imperial"');

        static::assertEquals(1, $metricCount);
        static::assertEquals(1, $imperialCount);
    }
}
