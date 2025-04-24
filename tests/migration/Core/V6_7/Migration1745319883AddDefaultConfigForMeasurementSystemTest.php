<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        // Clean up any existing data for the tested keys
        $this->connection->executeStatement('DELETE FROM system_config WHERE configuration_key IN (
            "core.measurementSystem.type",
            "core.measurementSystem.dimensionUnit",
            "core.measurementSystem.massUnit"
        )');

        $this->migration = new Migration1745319883AddDefaultConfigForMeasurementSystem();
    }

    public function testUpdate(): void
    {
        // Ensure the keys do not exist before the migration
        static::assertFalse($this->configExists('core.measurementSystem.type'));
        static::assertFalse($this->configExists('core.measurementSystem.dimensionUnit'));
        static::assertFalse($this->configExists('core.measurementSystem.massUnit'));

        // Run the migration
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        // Verify the inserted values
        $this->assertConfigValue('core.measurementSystem.type', '{"_value": "metric"}');
        $this->assertConfigValue('core.measurementSystem.dimensionUnit', '{"_value": "mm"}');
        $this->assertConfigValue('core.measurementSystem.massUnit', '{"_value": "kg"}');
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
