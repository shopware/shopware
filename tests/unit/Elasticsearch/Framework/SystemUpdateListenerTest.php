<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexingDto;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemUpdateListener::class)]
class SystemUpdateListenerTest extends TestCase
{
    public function testKeepsTheScheduleAndDispatchedBatchesWhenALaterReadFails(): void
    {
        $storage = new ArrayKeyValueStorage([SystemUpdateListener::CONFIG_KEY => ['product']]);

        $first = new ElasticsearchIndexingMessage(new IndexingDto(['product-a'], 'product-index', 'product'), null, Context::createDefaultContext());
        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer->method('iterateMessages')->willReturn((static function () use ($first): \Generator {
            yield $first;
            throw new \RuntimeException('Later catalog read failed');
        })());
        $bus = new CollectingMessageBus();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            $bus,
            static::createStub(IndexMappingUpdater::class),
            $logger,
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(1, $bus->getMessages());
        static::assertSame($first, $bus->getMessages()[0]->getMessage());
        static::assertSame(['product'], $storage->get(SystemUpdateListener::CONFIG_KEY));
    }

    public function testShouldDoNothingWhenNotSet(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $listener = new SystemUpdateListener(
            static::createStub(AbstractKeyValueStorage::class),
            static::createStub(ElasticsearchIndexer::class),
            $messageBus,
            $mappingUpdater,
            new NullLogger(),
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testShouldScheduleWithValues(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $storage = new ArrayKeyValueStorage([SystemUpdateListener::CONFIG_KEY => ['*']]);

        $listener = new SystemUpdateListener(
            $storage,
            $this->createIndexer(),
            $messageBus,
            $mappingUpdater,
            new NullLogger(),
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(1, $messageBus->getMessages());
        static::assertFalse($storage->has(SystemUpdateListener::CONFIG_KEY));
    }

    /**
     * @param array<string>|string $stored
     * @param array<string> $expected
     */
    #[DataProvider('storedEntitiesProvider')]
    public function testReindexesOnlyTheStoredEntities(array|string $stored, array $expected): void
    {
        $storage = static::createStub(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn($stored);

        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer
            ->expects($this->once())
            ->method('iterateMessages')
            ->with($expected);

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            new CollectingMessageBus(),
            static::createStub(IndexMappingUpdater::class),
            new NullLogger(),
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));
    }

    /**
     * @return iterable<string, array{array<string>|string, array<string>}>
     */
    public static function storedEntitiesProvider(): iterable
    {
        yield 'entity list' => [['product', 'category'], ['product', 'category']];
        yield 'json encoded entity list' => ['["product"]', ['product']];
        yield 'wildcard' => [['*'], []];
        yield 'json encoded wildcard' => ['["*"]', []];
        yield 'unregistered entity is dropped' => [['product', 'swag_removed'], ['product']];
    }

    public function testFailedMappingUpdateStillDispatchesReindex(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = static::createStub(IndexMappingUpdater::class);
        $mappingUpdater
            ->method('update')
            ->willThrowException(new \RuntimeException('mapping conflict'));

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn(['product']);
        $storage->expects($this->once())->method('remove')->with(SystemUpdateListener::CONFIG_KEY);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $listener = new SystemUpdateListener(
            $storage,
            $this->createIndexer(),
            $messageBus,
            $mappingUpdater,
            $logger,
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(1, $messageBus->getMessages());
    }

    public function testFailedReindexKeepsTheSchedule(): void
    {
        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn(['product']);
        $storage->expects($this->never())->method('remove');

        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer
            ->method('iterateMessages')
            ->willThrowException(new \RuntimeException('cluster unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            new CollectingMessageBus(),
            static::createStub(IndexMappingUpdater::class),
            $logger,
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));
    }

    public function testRemovesScheduleWithoutRegisteredEntities(): void
    {
        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->method('get')->willReturn(['swag_removed']);
        $storage->expects($this->once())->method('remove')->with(SystemUpdateListener::CONFIG_KEY);

        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer->expects($this->never())->method('iterateMessages');

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            new CollectingMessageBus(),
            static::createStub(IndexMappingUpdater::class),
            new NullLogger(),
            $this->createRegistry(),
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));
    }

    public function testErrorsAreNotSwallowed(): void
    {
        $mappingUpdater = static::createStub(IndexMappingUpdater::class);
        $mappingUpdater
            ->method('update')
            ->willThrowException(new \TypeError('programming error'));

        $listener = new SystemUpdateListener(
            static::createStub(AbstractKeyValueStorage::class),
            static::createStub(ElasticsearchIndexer::class),
            new CollectingMessageBus(),
            $mappingUpdater,
            new NullLogger(),
            $this->createRegistry(),
        );

        $this->expectException(\TypeError::class);

        $listener(static::createStub(UpdatePostFinishEvent::class));
    }

    private function createRegistry(): ElasticsearchRegistry
    {
        $product = static::createStub(AbstractElasticsearchDefinition::class);
        $product->method('getEntityDefinition')->willReturn(new ProductDefinition());

        $category = static::createStub(AbstractElasticsearchDefinition::class);
        $category->method('getEntityDefinition')->willReturn(new CategoryDefinition());

        return new ElasticsearchRegistry([$product, $category]);
    }

    private function createIndexer(): ElasticsearchIndexer
    {
        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer->method('iterateMessages')->willReturn((static function (): \Generator {
            yield new ElasticsearchIndexingMessage(new IndexingDto(['product-a'], 'product-index', 'product'), null, Context::createDefaultContext());
        })());

        return $indexer;
    }
}
