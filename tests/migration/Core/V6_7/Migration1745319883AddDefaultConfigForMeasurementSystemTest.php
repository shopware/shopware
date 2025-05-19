<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1742199548MeasurementSystem;
use Shopware\Core\Migration\V6_7\Migration1745319883AddDefaultConfigForMeasurementSystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1745319883AddDefaultConfigForMeasurementSystem::class)]
class Migration1745319883AddDefaultConfigForMeasurementSystemTest extends TestCase
{
    private Connection $connection;

    private Migration1745319883AddDefaultConfigForMeasurementSystem $migration;

    private Migration1742199548MeasurementSystem $migrationMeasurementSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        // Clean up any existing data for the tested keys
        $this->connection->executeStatement('DELETE FROM system_config WHERE configuration_key IN (
            "core.measurementSystem.typeId",
            "core.measurementSystem.lengthUnitId",
            "core.measurementSystem.weightUnitId"
        )');

        $this->migrationMeasurementSystem = new Migration1742199548MeasurementSystem();
        $this->migration = new Migration1745319883AddDefaultConfigForMeasurementSystem();
    }

    public function testUpdate(): void
    {
        $this->migrationMeasurementSystem->update($this->connection);

        // Ensure the keys do not exist before the migration
        static::assertFalse($this->configExists('core.measurementSystem.typeId'));
        static::assertFalse($this->configExists('core.measurementSystem.lengthUnitId'));
        static::assertFalse($this->configExists('core.measurementSystem.weightUnitId'));

        // Run the migration
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $metricId = $this->connection->fetchOne('SELECT id FROM `measurement_system` WHERE `technical_name` = "metric"');
        static::assertNotFalse($metricId);
        $this->assertConfigValue('core.measurementSystem.typeId', \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($metricId)));

        $units = $this->connection->fetchAllKeyValue('SELECT id, type FROM `measurement_display_unit` WHERE short_name IN (:names)', [
            'names' => ['mm', 'kg'],
        ], [
            'names' => ArrayParameterType::BINARY,
        ]);
        static::assertNotEmpty($units);

        foreach ($units as $id => $unitType) {
            $configKey = $unitType === 'length' ? 'core.measurementSystem.lengthUnitId' : 'core.measurementSystem.weightUnitId';
            $configValue = \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($id));

            $this->assertConfigValue($configKey, $configValue);
        }
    }

    private function configExists(string $key): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM system_config WHERE configuration_key = :key',
            ['key' => $key]
        );
    }

    private function assertConfigValue(string $key, string $expectedValue): void
    {
        $value = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key',
            ['key' => $key]
        );

        static::assertSame($expectedValue, $value);
    }
}
