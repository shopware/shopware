<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldSortingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('custom_field.repository');
    }

    public function testSortsCustomFieldPositionsNumerically(): void
    {
        $context = Context::createDefaultContext();
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->repository->create([
            ['id' => $ids[0], 'name' => 'sorting_test_ten', 'type' => 'text', 'config' => ['customFieldPosition' => 10, 'extensionConfiguration' => ['enabled' => true]]],
            ['id' => $ids[1], 'name' => 'sorting_test_two', 'type' => 'text', 'config' => ['customFieldPosition' => 2]],
            ['id' => $ids[2], 'name' => 'sorting_test_one', 'type' => 'text', 'config' => ['customFieldPosition' => 1]],
        ], $context);

        $criteria = new Criteria($ids);
        $criteria->addSorting(new FieldSorting('config.customFieldPosition', FieldSorting::ASCENDING));

        $fields = $this->repository->search($criteria, $context)->getEntities();

        static::assertSame([$ids[2], $ids[1], $ids[0]], $fields->getKeys());
        static::assertInstanceOf(CustomFieldEntity::class, $fields->get($ids[0]));
        $config = $fields->get($ids[0])->getConfig();
        static::assertIsArray($config);
        static::assertArrayHasKey('extensionConfiguration', $config);
        static::assertSame(['enabled' => true], $config['extensionConfiguration']);
    }

    public function testPreservesNumberBoundTypes(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->repository->create([
            [
                'id' => $id,
                'name' => 'number_bounds_test',
                'type' => 'float',
                'config' => [
                    'min' => 1,
                    'max' => 2.5,
                    'step' => 1,
                ],
            ],
        ], $context);

        $customField = $this->repository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(CustomFieldEntity::class, $customField);
        static::assertSame(['min' => 1, 'max' => 2.5, 'step' => 1], $customField->getConfig());
    }
}
