<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;

/**
 * @internal
 */
#[CoversClass(SystemUpdateListener::class)]
class SystemUpdateListenerTest extends TestCase
{
    public function testShouldDoNothingWhenNotSet(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $listener = new SystemUpdateListener(
            $this->createMock(AbstractKeyValueStorage::class),
            $this->createMock(ElasticsearchIndexer::class),
            $messageBus,
            $mappingUpdater
        );

        $listener($this->createMock(UpdatePostFinishEvent::class));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testShouldScheduleWithValues(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage
            ->method('get')
            ->willReturn(['*']);

        $message = $this->createMock(ElasticsearchIndexingMessage::class);
        $message->method('getOffset')
            ->willReturn($this->createMock(IndexerOffset::class));

        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer
            ->method('iterate')
            ->willReturnCallback(function ($offset) use ($message) {
                return $offset === null
                    ? $message
                    : null;
            });

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            $messageBus,
            $mappingUpdater
        );

        $listener($this->createMock(UpdatePostFinishEvent::class));

        static::assertCount(1, $messageBus->getMessages());
    }
}
