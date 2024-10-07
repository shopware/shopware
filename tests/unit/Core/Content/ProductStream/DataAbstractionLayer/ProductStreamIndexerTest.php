<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
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
    private Connection&MockObject $connection;

    private IteratorFactory&MockObject $iteratorFactory;

    private ProductDefinition&MockObject $productDefinition;

    private ProductStreamIndexer $indexer;

    private MockObject&EventDispatcherInterface $dispatcher;

    /**
     * @var StaticEntityRepository<ProductCollection>
     */
    private StaticEntityRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->productDefinition = $this->createMock(ProductDefinition::class);
        $this->repository = new StaticEntityRepository([], $this->productDefinition);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->indexer = new ProductStreamIndexer(
            $this->connection,
            $this->iteratorFactory,
            $this->repository,
            new Serializer([], [new JsonEncoder()]),
            $this->productDefinition,
            $this->dispatcher
        );
    }

    public function testGetName(): void
    {
        static::assertSame('product_stream.indexer', $this->indexer->getName());
    }

    public function testIterate(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(static::once())->method('fetchAllKeyValue')->willReturn([123]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(static::once())->method('executeQuery')->willReturn($result);

        $this->iteratorFactory->expects(static::once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $message = $this->indexer->iterate(['offset' => 10]);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
    }

    public function testUpdateReturnNull(): void
    {
        static::assertNull($this->indexer->update($this->createMock(EntityWrittenContainerEvent::class)));
    }

    public function testUpdate(): void
    {
        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects(static::once())->method('getPrimaryKeys')->willReturn([123]);

        static::assertInstanceOf(ProductStreamIndexingMessage::class, $this->indexer->update($event));
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

        $this->productDefinition->expects(static::exactly(5))->method('getEntityName')->willReturn('product');

        $statement = $this->createMock(Statement::class);
        $statement->expects(static::once())->method('executeStatement')->with([
            'serialized' => $serialized,
            'invalid' => 0,
            'id' => Uuid::fromHexToBytes($productStreamId),
        ]);

        $this->connection->expects(static::once())->method('fetchAllAssociative')->willReturn($filters);
        $this->connection->expects(static::once())->method('prepare')->willReturn($statement);

        $this->indexer->handle(new EntityIndexingMessage([$productStreamId]));
    }

    public function testGetTotal(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(static::once())->method('fetchOne')->willReturn(1);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(static::once())->method('getQueryPart')->willReturn(['id']);
        $queryBuilder->expects(static::once())->method('executeQuery')->willReturn($result);

        $this->iteratorFactory->expects(static::once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $total = $this->indexer->getTotal();
        static::assertEquals(1, $total);
    }
}
