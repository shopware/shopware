<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class ProductStreamRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product_stream.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream']], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertTrue($entity->isInvalid());
        static::assertNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream']], $this->context);
        $this->repository->upsert([['id' => $id, 'name' => 'New Name']], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertTrue($entity->isInvalid());
        static::assertNull($entity->getApiFilter());
        static::assertSame('New Name', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testCreateEntityWithFilters(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream', 'filters' => [['type' => 'contains', 'field' => 'name', 'value' => 'awesome']]]], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertFalse($entity->isInvalid());
        static::assertNotNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testCreateEntityWithMultiFilters(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'product.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertFalse($entity->isInvalid());
        static::assertNotNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
        static::assertEquals($data['filters'], $entity->getApiFilter());
    }

    public function testFetchFilters(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'product.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search($criteria, $this->context)->get($id);

        static::assertCount(4, $entity->getFilters());
        static::assertCount(1, $entity->getFilters()->filterByProperty('field', 'product.name'));
        static::assertCount(3, $entity->getFilters()->filterByProperty('type', 'multi'));
        static::assertCount(1, $entity->getFilters()->filterByProperty('type', 'multi')->filterByProperty('operator', 'AND'));
        static::assertCount(2, $entity->getFilters()->filterByProperty('type', 'multi')->filterByProperty('operator', 'OR'));
    }

    public function testFetchWithQueriesFilter(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'product.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_stream.filters.queries.queries.queries.field', 'product.name'));
        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search($criteria, $this->context)->get($id);

        static::assertNotNull($entity);
        static::assertSame('Test stream', $entity->getName());
    }
}
