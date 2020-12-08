<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductStreamIndexerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface|MockObject
     */
    private $productStreamRepository;

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
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->indexer = $this->getContainer()->get(ProductStreamIndexer::class);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'product.id',
                'value' => $productId,
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $multiId = Uuid::randomHex();
        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::fromHexToBytes($multiId),
                'type' => 'multi',
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'product.id',
                'operator' => 'equals',
                'value' => $productId,
                'position' => 1,
                'parent_id' => Uuid::fromHexToBytes($multiId),
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $multiId = Uuid::randomHex();
        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::fromHexToBytes($multiId),
                'type' => 'invalid',
                'field' => 'product.id',
                'value' => $productId,
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => null,
                'value' => $productId,
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'id',
                'value' => '',
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();
        $this->connection->insert(
            'product_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'range',
                'field' => 'price.gross',
                'parameters' => json_encode([RangeFilter::GTE => 10]),
                'position' => 1,
                'product_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('range', $entity->getApiFilter()[0]['type']);
        static::assertSame([RangeFilter::GTE => 10], $entity->getApiFilter()[0]['parameters']);
        static::assertFalse($entity->isInvalid());
    }

    private function createWrittenEvent(string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ProductStreamDefinition::ENTITY_NAME,
                    [new EntityWriteResult($id, [], ProductStreamDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT, null)],
                    Context::createDefaultContext()
                ),
            ]),
            []
        );
    }
}
