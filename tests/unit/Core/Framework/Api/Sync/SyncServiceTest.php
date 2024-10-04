<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncFkResolver;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SyncService::class)]
class SyncServiceTest extends TestCase
{
    public function testSyncSingleOperation(): void
    {
        $writeResult = new WriteResult(
            [
                'product' => [new EntityWriteResult('deleted-id', [], 'product', EntityWriteResult::OPERATION_DELETE)],
            ],
            [],
            [
                'product' => [new EntityWriteResult('created-id', [], 'product', EntityWriteResult::OPERATION_INSERT)],
            ]
        );

        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects(static::once())
            ->method('sync')
            ->willReturn($writeResult);

        $service = new SyncService(
            $writer,
            $this->createMock(EventDispatcherInterface::class),
            new StaticDefinitionInstanceRegistry(
                [ProductDefinition::class],
                $this->createMock(ValidatorInterface::class),
                $this->createMock(EntityWriteGatewayInterface::class),
            ),
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createMock(SyncFkResolver::class)
        );

        $upsert = new SyncOperation('foo', 'product', SyncOperation::ACTION_UPSERT, [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
            ['id' => '2'],
        ]);

        $behavior = new SyncBehavior('disable-indexing', ['product.indexer']);
        $result = $service->sync([$upsert, $delete], Context::createDefaultContext(), $behavior);

        static::assertSame([
            'product' => [
                'deleted-id',
            ],
        ], $result->getDeleted());

        static::assertSame([
            'product' => [
                'created-id',
            ],
        ], $result->getData());

        static::assertSame([], $result->getNotFound());
    }

    public function testCriteriaGetsNoLimit(): void
    {
        $ids = new IdsCollection();
        $operations = [
            new SyncOperation(
                key: 'foo',
                entity: 'product_category',
                action: SyncOperation::ACTION_DELETE,
                payload: [],
                criteria: [['type' => 'equals', 'field' => 'productId', 'value' => $ids->get('foo')]]
            ),
        ];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_category.productId', $ids->get('foo')));

        $registry = new StaticDefinitionInstanceRegistry(
            [ProductCategoryDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $searcher = $this->createMock(EntitySearcher::class);
        $searcher
            ->expects(static::once())
            ->method('search')
            ->with($registry->get(ProductCategoryDefinition::class), $criteria);

        $service = new SyncService(
            $this->createMock(EntityWriter::class),
            new EventDispatcher(),
            $registry,
            $searcher,
            new RequestCriteriaBuilder(
                new AggregationParser(),
                $this->createMock(ApiCriteriaValidator::class),
                new CriteriaArrayConverter(new AggregationParser()),
                100
            ),
            $this->createMock(SyncFkResolver::class)
        );

        $service->sync($operations, Context::createCLIContext(), new SyncBehavior());
    }

    public function testWildcardDeleteForMappingEntities(): void
    {
        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects(static::once())
            ->method('sync')
            ->willReturnCallback(function ($operations) {
                static::assertCount(1, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[0]);

                $operation = $operations[0];

                static::assertCount(4, $operation->getPayload());

                $map = \array_map(function (array $payload) {
                    return $payload['productId'] . '-' . $payload['categoryId'];
                }, $operation->getPayload());

                static::assertContains('product-1-category-1', $map);
                static::assertContains('product-1-category-2', $map);
                static::assertContains('product-2-category-1', $map);
                static::assertContains('product-2-category-2', $map);

                return new WriteResult([]);
            });

        $searcher = $this->createMock(EntitySearcherInterface::class);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);

        $filter = [
            ['type' => 'equalsAny', 'field' => 'productId', 'value' => ['product-1', 'product-2']],
        ];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productId', ['product-1', 'product-2']));

        $criteriaBuilder->expects(static::once())
            ->method('fromArray')
            ->with(['filter' => $filter])
            ->willReturn($criteria);

        $data = [
            ['primaryKey' => ['productId' => 'product-1', 'categoryId' => 'category-1'], 'data' => []],
            ['primaryKey' => ['productId' => 'product-1', 'categoryId' => 'category-2'], 'data' => []],
            ['primaryKey' => ['productId' => 'product-2', 'categoryId' => 'category-1'], 'data' => []],
            ['primaryKey' => ['productId' => 'product-2', 'categoryId' => 'category-2'], 'data' => []],
        ];

        $ids = new IdSearchResult(4, $data, new Criteria(), Context::createDefaultContext());

        $searcher->expects(static::once())
            ->method('search')
            ->willReturn($ids);

        $service = new SyncService(
            $writer,
            $this->createMock(EventDispatcherInterface::class),
            new StaticDefinitionInstanceRegistry(
                [ProductCategoryDefinition::class],
                $this->createMock(ValidatorInterface::class),
                $this->createMock(EntityWriteGatewayInterface::class),
            ),
            $searcher,
            $criteriaBuilder,
            $this->createMock(SyncFkResolver::class)
        );

        $delete = new SyncOperation(
            'delete-mapping',
            'product_category',
            SyncOperation::ACTION_DELETE,
            [],
            $filter
        );

        $behavior = new SyncBehavior('disable-indexing', ['product.indexer']);

        $service->sync([$delete], Context::createDefaultContext(), $behavior);
    }
}
