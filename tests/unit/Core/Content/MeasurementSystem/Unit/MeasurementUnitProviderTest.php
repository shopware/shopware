<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementSystemException;
use Shopware\Core\Content\MeasurementSystem\Unit\MeasurementUnitProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(MeasurementUnitProvider::class)]
class MeasurementUnitProviderTest extends TestCase
{
    private EntityRepository&MockObject $repository;

    private MeasurementUnitProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->provider = new MeasurementUnitProvider($this->repository);
    }

    public function testGetUnitInfoExistingUnit(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('mm', 'length', 1.0, 2),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        $unitInfo = $this->provider->getUnitInfo('mm');

        $expected = [
            'factor' => 1.0,
            'type' => 'length',
            'precision' => 2,
        ];

        static::assertSame($expected, [
            'factor' => $unitInfo->factor,
            'type' => $unitInfo->type,
            'precision' => $unitInfo->precision,
        ]);

        // Second call should use cache, no repository call

        $unitInfo = $this->provider->getUnitInfo('mm');
        static::assertSame($expected, [
            'factor' => $unitInfo->factor,
            'type' => $unitInfo->type,
            'precision' => $unitInfo->precision,
        ]);
    }

    public function testGetUnitInfoNonExistingUnit(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('mm', 'length', 1.0, 2),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        static::expectException(MeasurementSystemException::class);
        static::expectExceptionMessage('The measurement system unit "nonexistent" is not supported. Possible units are: mm');

        $this->provider->getUnitInfo('nonexistent');
    }

    public function testGetUnitInfoAfterReset(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('mm', 'length', 1.0, 2),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->exactly(2))
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->exactly(2))
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        // First call to populate cache
        $this->provider->getUnitInfo('mm');

        // Reset cache
        $this->provider->reset();

        // Second call should trigger repository query again
        $unitInfo = $this->provider->getUnitInfo('mm');

        $expected = [
            'factor' => 1.0,
            'type' => 'length',
            'precision' => 2,
        ];

        static::assertSame($expected, [
            'factor' => $unitInfo->factor,
            'type' => $unitInfo->type,
            'precision' => $unitInfo->precision,
        ]);
    }

    public function testReset(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('mm', 'length', 1.0, 2),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->exactly(2))
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->exactly(2))
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        // First call
        $this->provider->getUnitInfo('mm');

        // Reset
        $this->provider->reset();

        // Second call should query repository again
        $this->provider->getUnitInfo('mm');
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->provider->getDecorated();
    }

    public function testFloatConversions(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('test', 'test_type', 123.456789, 5),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        $unitInfo = $this->provider->getUnitInfo('test');

        static::assertSame(123.456789, $unitInfo->factor);
        static::assertSame('test_type', $unitInfo->type);
        static::assertSame(5, $unitInfo->precision);
    }

    public function testMultipleUnitTypes(): void
    {
        $entities = $this->createEntityCollection([
            $this->createMeasurementDisplayUnitEntity('mm', 'length', 1.0, 2),
            $this->createMeasurementDisplayUnitEntity('kg', 'weight', 1000.0, 3),
            $this->createMeasurementDisplayUnitEntity('celsius', 'temperature', 1.0, 1),
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        $mmUnit = $this->provider->getUnitInfo('mm');
        $kgUnit = $this->provider->getUnitInfo('kg');
        $celsiusUnit = $this->provider->getUnitInfo('celsius');

        static::assertSame(2, $mmUnit->precision);
        static::assertSame(3, $kgUnit->precision);
        static::assertSame(1, $celsiusUnit->precision);

        static::assertSame('length', $mmUnit->type);
        static::assertSame('weight', $kgUnit->type);
        static::assertSame('temperature', $celsiusUnit->type);
    }

    private function createMeasurementDisplayUnitEntity(string $shortName, string $type, float $factor, int $precision): MeasurementDisplayUnitEntity
    {
        $entity = new MeasurementDisplayUnitEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->shortName = $shortName;
        $entity->type = $type;
        $entity->factor = $factor;
        $entity->precision = $precision;

        return $entity;
    }

    /**
     * @param MeasurementDisplayUnitEntity[] $entities
     *
     * @return EntityCollection<MeasurementDisplayUnitEntity>
     */
    private function createEntityCollection(array $entities): EntityCollection
    {
        $collection = new EntityCollection();
        foreach ($entities as $entity) {
            $collection->add($entity);
        }

        return $collection;
    }
}
