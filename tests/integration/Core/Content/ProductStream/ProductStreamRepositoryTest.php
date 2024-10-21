<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductStream;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
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
#[Package('services-settings')]
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

        /** @var ProductStreamFilterCollection $filters */
        $filters = $entity->getFilters();
        static::assertNotNull($filters);

        static::assertCount(4, $filters);
        static::assertCount(1, $filters->filterByProperty('field', 'product.name')->getElements());
        static::assertCount(3, $filters->filterByProperty('type', 'multi')->getElements());
        static::assertCount(1, $filters->filterByProperty('type', 'multi')->filterByProperty('operator', 'AND')->getElements());
        static::assertCount(2, $filters->filterByProperty('type', 'multi')->filterByProperty('operator', 'OR')->getElements());
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
