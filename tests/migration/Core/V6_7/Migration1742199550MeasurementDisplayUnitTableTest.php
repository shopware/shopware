<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1742199549MeasurementSystemTable;
use Shopware\Core\Migration\V6_7\Migration1742199550MeasurementDisplayUnitTable;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1742199550MeasurementDisplayUnitTable::class)]
class Migration1742199550MeasurementDisplayUnitTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_display_unit`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `measurement_system`');

        // Create measurement_system tables first as they are required for foreign key constraint
        $systemMigration = new Migration1742199549MeasurementSystemTable();
        $systemMigration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1742199550', (new Migration1742199550MeasurementDisplayUnitTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $sm = $this->connection->createSchemaManager();

        static::assertFalse($sm->tablesExist(['measurement_display_unit']));
        static::assertFalse($sm->tablesExist(['measurement_display_unit_translation']));

        $migration = new Migration1742199550MeasurementDisplayUnitTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($sm->tablesExist(['measurement_display_unit']));
        static::assertTrue($sm->tablesExist(['measurement_display_unit_translation']));

        // Check that default units were created
        $unitCount = $this->connection->fetchOne('SELECT COUNT(*) FROM `measurement_display_unit`');
        static::assertGreaterThan(0, $unitCount);
    }
}
