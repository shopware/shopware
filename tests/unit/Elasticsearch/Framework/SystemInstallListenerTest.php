<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopware\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopware\Elasticsearch\Framework\SystemInstallListener;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemInstallListener::class)]
class SystemInstallListenerTest extends TestCase
{
    public function testDoesNothingWhenIndexerReturnsNoMessages(): void
    {
        $messageBus = new CollectingMessageBus();

        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer->expects($this->once())
            ->method('iterate')
            ->with(null)
            ->willReturn(null);

        $listener = new SystemInstallListener($indexer, $messageBus);
        $listener(new SystemInstallCompletedEvent(Context::createCLIContext()));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testDispatchesIndexingMessages(): void
    {
        $messageBus = new CollectingMessageBus();

        $message = static::createStub(ElasticsearchIndexingMessage::class);
        $message->method('getOffset')
            ->willReturn(static::createStub(IndexerOffset::class));

        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer->method('iterate')
            ->willReturnCallback(static function ($offset) use ($message) {
                return $offset === null
                    ? $message
                    : null;
            });

        $listener = new SystemInstallListener($indexer, $messageBus);
        $listener(new SystemInstallCompletedEvent(Context::createCLIContext()));

        static::assertCount(1, $messageBus->getMessages());
    }

    public function testDoesNotFailInstallWhenIndexingThrows(): void
    {
        $messageBus = new CollectingMessageBus();

        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer->expects($this->once())
            ->method('iterate')
            ->willThrowException(new \RuntimeException('OpenSearch is down'));

        $listener = new SystemInstallListener($indexer, $messageBus);
        $listener(new SystemInstallCompletedEvent(Context::createCLIContext()));

        static::assertCount(0, $messageBus->getMessages());
    }
}
