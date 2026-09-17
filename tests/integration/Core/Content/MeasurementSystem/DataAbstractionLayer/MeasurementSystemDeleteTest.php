<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MeasurementSystem\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class MeasurementSystemDeleteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<MeasurementSystemEntity>>
     */
    private EntityRepository $systemRepository;

    /**
     * @var EntityRepository<EntityCollection<MeasurementDisplayUnitEntity>>
     */
    private EntityRepository $unitRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->systemRepository = static::getContainer()->get('measurement_system.repository');
        $this->unitRepository = static::getContainer()->get('measurement_display_unit.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testDeletingAMeasurementSystemCascadesToItsDisplayUnits(): void
    {
        $ids = new IdsCollection();
        $this->createSystemWithUnits($ids);

        $deleted = $this->systemRepository->delete([['id' => $ids->get('system')]], $this->context);

        static::assertContains($ids->get('system'), $deleted->getDeletedPrimaryKeys('measurement_system'));
        static::assertContains($ids->get('unit-1'), $deleted->getDeletedPrimaryKeys('measurement_display_unit'));
        static::assertContains($ids->get('unit-2'), $deleted->getDeletedPrimaryKeys('measurement_display_unit'));

        static::assertSame(0, $this->unitRepository->searchIds(new Criteria($ids->getList(['unit-1', 'unit-2'])), $this->context)->getTotal());
    }

    public function testDeletingADisplayUnitKeepsItsMeasurementSystem(): void
    {
        $ids = new IdsCollection();
        $this->createSystemWithUnits($ids);

        $deleted = $this->unitRepository->delete([['id' => $ids->get('unit-1')]], $this->context);

        static::assertContains($ids->get('unit-1'), $deleted->getDeletedPrimaryKeys('measurement_display_unit'));
        static::assertSame([], $deleted->getDeletedPrimaryKeys('measurement_system'));

        static::assertSame([$ids->get('system')], $this->systemRepository->searchIds(new Criteria([$ids->get('system')]), $this->context)->getIds());
        static::assertSame([$ids->get('unit-2')], $this->unitRepository->searchIds(new Criteria($ids->getList(['unit-1', 'unit-2'])), $this->context)->getIds());
    }

    private function createSystemWithUnits(IdsCollection $ids): void
    {
        $this->systemRepository->create([
            [
                'id' => $ids->create('system'),
                'technicalName' => 'test-system-' . $ids->get('system'),
                'name' => 'Test system',
                'units' => [
                    $this->unit($ids, 'unit-1', isDefault: true),
                    $this->unit($ids, 'unit-2', isDefault: false),
                ],
            ],
        ], $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function unit(IdsCollection $ids, string $key, bool $isDefault): array
    {
        return [
            'id' => $ids->create($key),
            'default' => $isDefault,
            'type' => 'length',
            'shortName' => $key . '-' . substr($ids->get($key), -6),
            'factor' => 1.0,
            'precision' => 2,
            'name' => 'Test unit',
        ];
    }
}
