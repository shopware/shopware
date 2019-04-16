<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\ProductStream\Util\EventIdExtractor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductStreamIndexerTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var EventIdExtractor|MockObject
     */
    private $eventIdExtractor;

    /**
     * @var EntityRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var ProductStreamIndexer
     */
    private $indexer;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepo;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->productRepo = $this->getContainer()->get('product.repository');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventIdExtractor = $this->createMock(EventIdExtractor::class);
        $this->repository = $this->getContainer()->get('product_stream.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $serializer = $this->getContainer()->get('serializer');
        $cacheKeyGenerator = $this->getContainer()->get(EntityCacheKeyGenerator::class);
        $cache = $this->getContainer()->get('shopware.cache');
        $this->indexer = new ProductStreamIndexer(
            $eventDispatcher, $this->eventIdExtractor, $this->repository, $this->connection,
            $serializer, $cacheKeyGenerator, $cache
        );
    }

    public function testValidRefresh(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), NOW())',
                Uuid::randomHex(), 'equals', 'product.id', $productId, $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);
        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('equals', $entity->getApiFilter()[0]['type']);
        static::assertSame('product.id', $entity->getApiFilter()[0]['field']);
        static::assertSame($productId, $entity->getApiFilter()[0]['value']);
        static::assertFalse($entity->isInvalid());
    }

    public function testWithChildren(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $multiId = Uuid::randomHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', 1, UNHEX(\'%s\'), NOW())',
                $multiId, 'multi', $id
            )
        );
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, operator, value, position, parent_id, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), UNHEX(\'%s\'), NOW())',
                Uuid::randomHex(), 'equals', 'product.id', 'equals', $productId, $multiId, $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);
        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('multi', $entity->getApiFilter()[0]['type']);
        static::assertSame(MultiFilter::CONNECTION_AND, $entity->getApiFilter()[0]['operator']);
        static::assertCount(1, $entity->getApiFilter()[0]['queries']);
        static::assertSame('equals', $entity->getApiFilter()[0]['queries'][0]['type']);
        static::assertSame('product.id', $entity->getApiFilter()[0]['queries'][0]['field']);
        static::assertSame($productId, $entity->getApiFilter()[0]['queries'][0]['value']);
        static::assertFalse($entity->isInvalid());
    }

    public function testInvalidType(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $multiId = Uuid::randomHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), NOW())',
                $multiId, 'invalid', 'product.id', $productId, $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getApiFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyField(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $multiId = Uuid::randomHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), NOW())',
                $multiId, 'equals', null, $productId, $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getApiFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyValue(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $multiId = Uuid::randomHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, value, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), NOW())',
                $multiId, 'equals', 'id', null, $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNull($entity->getApiFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testWithParameters(): void
    {
        $productId = Uuid::randomHex();
        $this->productRepo->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ], $this->context
        );
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $id = Uuid::randomHex();
        $this->connection->exec(
            sprintf('INSERT INTO product_stream (id, created_at, api_filter, invalid) VALUES (UNHEX(\'%s\'), NOW(), null, 1)', $id)
        );
        $this->connection->exec(
            sprintf('INSERT INTO product_stream_translation (product_stream_id, language_id, name, created_at) VALUES (UNHEX(\'%s\'), UNHEX(\'%s\'), \'%s\', NOW())', $id, $languageId, 'Stream')
        );
        $multiId = Uuid::randomHex();
        $this->connection->exec(
            sprintf(
                'INSERT INTO product_stream_filter (id, type, field, parameters, position, product_stream_id, created_at) VALUES (UNHEX(\'%s\'), \'%s\', \'%s\', \'%s\', 1, UNHEX(\'%s\'), NOW())',
                $multiId, 'range', 'price.gross', json_encode([RangeFilter::GTE => 10]), $id
            )
        );

        $this->eventIdExtractor->expects(static::once())->method('getProductStreamIds')->willReturn([$id]);

        $this->indexer->refresh(new EntityWrittenContainerEvent($this->context, $this->createMock(NestedEventCollection::class), []));

        /** @var ProductStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('range', $entity->getApiFilter()[0]['type']);
        static::assertSame([RangeFilter::GTE => 10], $entity->getApiFilter()[0]['parameters']);
        static::assertFalse($entity->isInvalid());
    }
}
