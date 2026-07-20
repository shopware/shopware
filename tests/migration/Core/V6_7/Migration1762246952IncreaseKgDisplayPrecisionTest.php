<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1742199549MeasurementSystemTable;
use Shopware\Core\Migration\V6_7\Migration1742199550MeasurementDisplayUnitTable;
use Shopware\Core\Migration\V6_7\Migration1762246952IncreaseKgDisplayPrecision;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1762246952IncreaseKgDisplayPrecision::class)]
class Migration1762246952IncreaseKgDisplayPrecisionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        (new Migration1742199549MeasurementSystemTable())->update($this->connection);
        (new Migration1742199550MeasurementDisplayUnitTable())->update($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'UPDATE measurement_display_unit SET `precision` = :precision WHERE short_name = :shortName;',
            [
                'precision' => 2,
                'shortName' => 'kg',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        $this->connection->executeStatement(
            'UPDATE measurement_display_unit SET `precision` = :precision WHERE short_name = :shortName;',
            [
                'precision' => 2,
                'shortName' => 'mm',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1762246952, (new Migration1762246952IncreaseKgDisplayPrecision())->getCreationTimestamp());
    }

    public function testMigrationIncreasesKgPrecision(): void
    {
        $this->connection->executeStatement(
            'UPDATE measurement_display_unit SET `precision` = 2, updated_at = NULL WHERE short_name = :shortName',
            [
                'shortName' => 'kg',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        $migration = new Migration1762246952IncreaseKgDisplayPrecision();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $precision = $this->connection->fetchOne(
            'SELECT `precision` FROM measurement_display_unit WHERE short_name = :shortName',
            [
                'shortName' => 'kg',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        static::assertSame('6', (string) $precision);
    }

    public function testMigrationIncreasesMmPrecision(): void
    {
        $this->connection->executeStatement(
            'UPDATE measurement_display_unit SET `precision` = 2, updated_at = NULL WHERE short_name = :shortName',
            [
                'shortName' => 'mm',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        $migration = new Migration1762246952IncreaseKgDisplayPrecision();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $precision = $this->connection->fetchOne(
            'SELECT `precision` FROM measurement_display_unit WHERE short_name = :shortName',
            [
                'shortName' => 'mm',
            ],
            [
                'shortName' => ParameterType::STRING,
            ]
        );

        static::assertSame('3', (string) $precision);
    }

    public function testMigrationDoesNotChangeUpdatedUnits(): void
    {
        $this->connection->executeStatement(
            'UPDATE measurement_display_unit SET `precision` = 2, updated_at = :now WHERE short_name = :shortName',
            [
                'shortName' => 'kg',
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        $migration = new Migration1762246952IncreaseKgDisplayPrecision();

        $migration->update($this->connection);

        $precision = $this->connection->fetchOne(
            'SELECT `precision` FROM measurement_display_unit WHERE short_name = :shortName',
            [
                'shortName' => 'kg',
            ],
            [
                'shortName' => ParameterType::STRING,
            ],
        );

        static::assertSame('2', (string) $precision);
    }
}
