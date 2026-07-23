<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
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
#[Package('framework')]
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
            static::createStub(AbstractKeyValueStorage::class),
            static::createStub(ElasticsearchIndexer::class),
            $messageBus,
            $mappingUpdater
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

        $storage = static::createStub(AbstractKeyValueStorage::class);
        $storage
            ->method('get')
            ->willReturn(['*']);

        $message = static::createStub(ElasticsearchIndexingMessage::class);
        $message->method('getOffset')
            ->willReturn(static::createStub(IndexerOffset::class));

        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer
            ->method('iterate')
            ->willReturnCallback(static function ($offset) use ($message) {
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

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(1, $messageBus->getMessages());
    }
}
