<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStreamIndexer::class)]
class ProductStreamIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    private IteratorFactory&Stub $iteratorFactory;

    private ProductDefinition&Stub $productDefinition;

    private ProductStreamIndexer $indexer;

    private Stub&EventDispatcherInterface $dispatcher;

    /**
     * @var StaticEntityRepository<ProductStreamCollection>
     */
    private StaticEntityRepository $repository;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->iteratorFactory = static::createStub(IteratorFactory::class);
        $this->productDefinition = static::createStub(ProductDefinition::class);
        $this->repository = new StaticEntityRepository([], new ProductStreamDefinition());
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);

        $this->indexer = $this->createIndexer();
    }

    public function testGetName(): void
    {
        static::assertSame('product_stream.indexer', $this->indexer->getName());
    }

    public function testIterate(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllKeyValue')->willReturn([123]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $message = $this->createIndexer(iteratorFactory: $iteratorFactory)->iterate(['offset' => 10]);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
    }

    public function testUpdateReturnNull(): void
    {
        static::assertNull($this->indexer->update(static::createStub(EntityWrittenContainerEvent::class)));
    }

    public function testUpdate(): void
    {
        $streamId = Uuid::randomHex();
        $deletedStreamId = Uuid::randomHex();

        $message = $this->indexer->update(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ProductStreamDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $streamId,
                            [],
                            ProductStreamDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
                new EntityWrittenEvent(
                    'product_stream_filter',
                    [
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            ['productStreamId' => $streamId],
                            'product_stream_filter',
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            [],
                            'product_stream_filter',
                            EntityWriteResult::OPERATION_DELETE,
                            new EntityExistence(
                                'product_stream_filter',
                                ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                                true,
                                false,
                                false,
                                ['product_stream_id' => Uuid::fromHexToBytes($deletedStreamId)]
                            ),
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));

        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        static::assertSame([$streamId, $deletedStreamId], $message->getData());
    }

    public function testHandle(): void
    {
        $productStreamId = Uuid::randomHex();
        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();
        $filterId3 = Uuid::randomHex();

        $filters = [
            [
                'array_key' => $productStreamId,
                'id' => $filterId1,
                'product_stream_id' => $productStreamId,
                'parent_id' => null,
                'type' => 'multi',
                'field' => null,
                'operator' => 'OR',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $productStreamId,
                'id' => $filterId2,
                'entity_stream_id' => $productStreamId,
                'parent_id' => $filterId1,
                'type' => 'multi',
                'field' => null,
                'operator' => 'AND',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $productStreamId,
                'id' => $filterId3,
                'entity_stream_id' => $productStreamId,
                'parent_id' => $filterId2,
                'type' => 'not',
                'field' => null,
                'operator' => null,
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $productStreamId,
                'id' => Uuid::randomHex(),
                'entity_stream_id' => $productStreamId,
                'parent_id' => $filterId3,
                'type' => 'equalsAny',
                'field' => 'id',
                'operator' => null,
                'value' => '0189de3825ae719d9a08eeea48d6e13a',
                'parameters' => null,
                'position' => '0',
            ],
        ];

        $query = new MultiFilter(MultiFilter::CONNECTION_OR, [
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new NotFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_AND, [
                        new EqualsAnyFilter('product.id', ['0189de3825ae719d9a08eeea48d6e13a']),
                        new EqualsAnyFilter('product.parentId', ['0189de3825ae719d9a08eeea48d6e13a']),
                    ]),
                ]),
            ]),
        ]);
        $serialized = \json_encode([QueryStringParser::toArray($query)]);

        $productDefinition = $this->createMock(ProductDefinition::class);
        $productDefinition->expects($this->exactly(8))->method('getEntityName')->willReturn('product');

        $statement = $this->createMock(Statement::class);
        $params = [
            ['serialized', $serialized],
            ['invalid', 0],
            ['id', Uuid::fromHexToBytes($productStreamId)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn($filters);
        $connection->expects($this->once())->method('prepare')->willReturn($statement);

        $this->createIndexer(connection: $connection, productDefinition: $productDefinition)->handle(new EntityIndexingMessage([$productStreamId]));
    }

    public function testHandleSkipsEmptyIdFilters(): void
    {
        $productStreamId = Uuid::randomHex();
        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();
        $filterId3 = Uuid::randomHex();

        $filters = [
            [
                'array_key' => $productStreamId,
                'id' => $filterId1,
                'product_stream_id' => $productStreamId,
                'parent_id' => null,
                'type' => 'multi',
                'field' => null,
                'operator' => 'OR',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $productStreamId,
                'id' => $filterId2,
                'entity_stream_id' => $productStreamId,
                'parent_id' => $filterId1,
                'type' => 'multi',
                'field' => null,
                'operator' => 'AND',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $productStreamId,
                'id' => $filterId3,
                'entity_stream_id' => $productStreamId,
                'parent_id' => $filterId2,
                'type' => 'equals',
                'field' => 'id',
                'operator' => null,
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
        ];

        $statement = $this->createMock(Statement::class);
        $params = [
            ['serialized', '[]'],
            ['invalid', 0],
            ['id', Uuid::fromHexToBytes($productStreamId)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn($filters);
        $connection->expects($this->once())->method('prepare')->willReturn($statement);

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([$productStreamId]));
    }

    public function testGetTotal(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchOne')->willReturn(1);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('getSelectParts')->willReturn(['id']);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $total = $this->createIndexer(iteratorFactory: $iteratorFactory)->getTotal();
        static::assertSame(1, $total);
    }

    private function createIndexer(
        ?Connection $connection = null,
        ?IteratorFactory $iteratorFactory = null,
        ?ProductDefinition $productDefinition = null,
    ): ProductStreamIndexer {
        return new ProductStreamIndexer(
            $connection ?? $this->connection,
            $iteratorFactory ?? $this->iteratorFactory,
            $this->repository,
            new Serializer([], [new JsonEncoder()]),
            $productDefinition ?? $this->productDefinition,
            $this->dispatcher
        );
    }
}
