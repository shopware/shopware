<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\UnitProvider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\UnitProvider\MeasurementUnitProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MeasurementUnitProvider::class)]
class MeasurementUnitProviderTest extends TestCase
{
    private Connection&MockObject $connection;

    private MeasurementUnitProvider $provider;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->provider = new MeasurementUnitProvider($this->connection);
    }

    public function testGetUnitsFirstCall(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
            'cm' => [
                'type' => 'length',
                'factor' => '10.0',
                'precision' => '1',
            ],
            'kg' => [
                'type' => 'weight',
                'factor' => '1000.0',
                'precision' => '3',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        $units = $this->provider->getUnits();

        $expected = [
            'mm' => [
                'factor' => 1.0,
                'type' => 'length',
                'precision' => 2,
            ],
            'cm' => [
                'factor' => 10.0,
                'type' => 'length',
                'precision' => 1,
            ],
            'kg' => [
                'factor' => 1000.0,
                'type' => 'weight',
                'precision' => 3,
            ],
        ];

        static::assertSame($expected, $units);
    }

    public function testGetUnitsSecondCallUsesCache(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        // First call
        $firstCall = $this->provider->getUnits();

        // Second call should use cache, no DB call
        $secondCall = $this->provider->getUnits();

        static::assertSame($firstCall, $secondCall);
    }

    public function testGetUnitsEmptyResult(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn([]);

        $units = $this->provider->getUnits();

        static::assertSame([], $units);
    }

    public function testGetUnitInfoExistingUnit(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        $unitInfo = $this->provider->getUnitInfo('mm');

        $expected = [
            'factor' => 1.0,
            'type' => 'length',
            'precision' => 2,
        ];

        static::assertSame($expected, $unitInfo);
    }

    public function testGetUnitInfoNonExistingUnit(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        static::expectException(MeasurementSystemException::class);
        static::expectExceptionMessage('The measurement system unit "nonexistent" is not supported. Possible units are: mm');

        $this->provider->getUnitInfo('nonexistent');
    }

    public function testGetUnitInfoAfterReset(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
        ];

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        // First call to populate cache
        $this->provider->getUnitInfo('mm');

        // Reset cache
        $this->provider->reset();

        // Second call should trigger DB query again
        $unitInfo = $this->provider->getUnitInfo('mm');

        $expected = [
            'factor' => 1.0,
            'type' => 'length',
            'precision' => 2,
        ];

        static::assertSame($expected, $unitInfo);
    }

    public function testReset(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
        ];

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        // First call
        $this->provider->getUnits();

        // Reset
        $this->provider->reset();

        // Second call should query DB again
        $this->provider->getUnits();
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->provider->getDecorated();
    }

    public function testFloatConversions(): void
    {
        $rawUnits = [
            'test' => [
                'type' => 'test_type',
                'factor' => '123.456789',
                'precision' => '5',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        $unitInfo = $this->provider->getUnitInfo('test');

        static::assertSame(123.456789, $unitInfo['factor']);
        static::assertSame('test_type', $unitInfo['type']);
        static::assertSame(5, $unitInfo['precision']);
    }

    public function testMultipleUnitTypes(): void
    {
        $rawUnits = [
            'mm' => [
                'type' => 'length',
                'factor' => '1.0',
                'precision' => '2',
            ],
            'kg' => [
                'type' => 'weight',
                'factor' => '1000.0',
                'precision' => '3',
            ],
            'celsius' => [
                'type' => 'temperature',
                'factor' => '1.0',
                'precision' => '1',
            ],
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociativeIndexed')
            ->with('SELECT `short_name`, `type`, `factor`, `precision` FROM measurement_display_unit')
            ->willReturn($rawUnits);

        $units = $this->provider->getUnits();

        static::assertCount(3, $units);
        static::assertArrayHasKey('mm', $units);
        static::assertArrayHasKey('kg', $units);
        static::assertArrayHasKey('celsius', $units);

        static::assertSame('length', $units['mm']['type']);
        static::assertSame('weight', $units['kg']['type']);
        static::assertSame('temperature', $units['celsius']['type']);
    }
}
