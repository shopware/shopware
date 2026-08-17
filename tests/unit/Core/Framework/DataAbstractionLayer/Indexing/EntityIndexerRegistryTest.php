<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\PostUpdateIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityIndexerRegistry::class)]
class EntityIndexerRegistryTest extends TestCase
{
    private MessageBusInterface&Stub $messageBusMock;

    private EventDispatcherInterface&Stub $dispatcherMock;

    private EntityIndexer&Stub $indexerMock1;

    private EntityIndexer&Stub $indexerMock2;

    private IndexerMetricsInstrumentor&Stub $instrumentorStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBusMock = static::createStub(MessageBusInterface::class);
        $this->dispatcherMock = static::createStub(EventDispatcherInterface::class);
        $this->indexerMock1 = static::createStub(EntityIndexer::class);
        $this->indexerMock2 = static::createStub(EntityIndexer::class);
        $this->instrumentorStub = static::createStub(IndexerMetricsInstrumentor::class);
    }

    public function testIndexSuccessful(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) {
                if ($event instanceof ProgressStartedEvent || $event instanceof ProgressFinishedEvent) {
                    return $event;
                }

                return null;
            });

        $registry = new EntityIndexerRegistry([$this->indexerMock1, $this->indexerMock2], $this->messageBusMock, $dispatcher, $this->instrumentorStub);
        $registry->index(false);
    }

    public function testIndexSuccessfulFullEntity(): void
    {
        $fullEntityIndexerMessageMock = $this->createMock(FullEntityIndexerMessage::class);

        $skip = ['indexer1'];
        $only = ['indexer2'];

        $indexers = [$this->indexerMock1, $this->indexerMock2];

        $registryMock = $this->getMockBuilder(EntityIndexerRegistry::class)
            ->setConstructorArgs([$indexers, $this->messageBusMock, $this->dispatcherMock, $this->instrumentorStub])
            ->onlyMethods(['index'])
            ->getMock();

        $registryMock->expects($this->once())
            ->method('index')
            ->with(true, $skip, $only);

        $fullEntityIndexerMessageMock->expects($this->once())
            ->method('getSkip')
            ->willReturn($skip);

        $fullEntityIndexerMessageMock->expects($this->once())
            ->method('getOnly')
            ->willReturn($only);

        $registryMock->__invoke($fullEntityIndexerMessageMock);
    }

    public function testIndexWithSkipAndOnlyParameters(): void
    {
        $skip = ['indexer1'];
        $only = ['indexer2'];

        $indexer1 = $this->createMock(EntityIndexer::class);
        $indexer1->method('getName')->willReturn('indexer1');
        $indexer2 = $this->createMock(EntityIndexer::class);
        $indexer2->method('getName')->willReturn('indexer2');

        $indexer1->expects($this->never())->method('iterate');
        $indexer2->expects($this->atLeastOnce())->method('iterate');

        $registry = new EntityIndexerRegistry([$indexer1, $indexer2], $this->messageBusMock, $this->dispatcherMock, $this->instrumentorStub);
        $registry->index(false, $skip, $only);
    }

    public function testGetIndexersReturnsNormalIndexersAndOptions(): void
    {
        $normalIndexer = static::createStub(EntityIndexer::class);
        $normalIndexer->method('getName')->willReturn('normal.indexer');
        $normalIndexer->method('getOptions')->willReturn(['normal.option']);

        $postUpdateIndexer = static::createStub(PostUpdateIndexer::class);
        $postUpdateIndexer->method('getName')->willReturn('post-update.indexer');

        $registry = new EntityIndexerRegistry(
            [$normalIndexer, $postUpdateIndexer],
            $this->messageBusMock,
            $this->dispatcherMock,
            $this->instrumentorStub,
        );

        static::assertSame(['normal.indexer' => ['normal.option']], $registry->getIndexers());
    }

    public function testRefreshMethod(): void
    {
        $eventMock = $this->createMock(EntityWrittenContainerEvent::class);
        $context = Context::createDefaultContext();
        $skipEntity = new ArrayEntity(['skips' => ['skip1', 'skip2']]);
        $onlyEntity = new ArrayEntity(['onlies' => ['skip1', 'skip3', 'skip4']]);
        $messageMock = $this->createMock(EntityIndexingMessage::class);

        $indexer1 = $this->createMock(EntityIndexer::class);
        $indexer1->method('getName')->willReturn('indexer1');
        $indexer1->method('getOptions')->willReturn(['skip1', 'skip2', 'skip3', 'skip4', 'skip5']);
        $this->indexerMock2->method('getName')->willReturn('indexer2');

        $eventMock->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, $skipEntity);
        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY, $onlyEntity);

        $indexer1->expects($this->once())
            ->method('update')
            ->with($eventMock)
            ->willReturn($messageMock);

        $messageMock->expects($this->once())
            ->method('setIndexer')
            ->with('indexer1');

        $messageMock
            ->method('setSkip')
            ->with(static::callback(
                static function (array $skips) {
                    sort($skips);

                    return $skips === ['skip2', 'skip5'];
                }
            ));

        $messageMock->expects($this->once())
            ->method('addSkip')
            ->with('skip1', 'skip2');

        $registry = new EntityIndexerRegistry([$indexer1, $this->indexerMock2], $this->messageBusMock, $this->dispatcherMock, $this->instrumentorStub);
        $registry->refresh($eventMock);
    }

    public function testHandleIsRoutedThroughTheMetricsInstrumentor(): void
    {
        $message = new EntityIndexingMessage(['id-1'], null, null, false, true);
        $message->setIndexer('indexer1');

        $indexer1 = $this->createMock(EntityIndexer::class);
        $indexer1->method('getName')->willReturn('indexer1');
        $this->indexerMock2->method('getName')->willReturn('indexer2');

        $metricsInstrumentor = $this->createMock(IndexerMetricsInstrumentor::class);
        $metricsInstrumentor->expects($this->once())
            ->method('measureRun')
            ->with($indexer1, $message, static::isInstanceOf(\Closure::class))
            // the collaborator owns the invocation; run the callback so the indexer still handles the message
            ->willReturnCallback(static fn (EntityIndexer $indexer, EntityIndexingMessage $msg, \Closure $callback) => $callback());

        $indexer1->expects($this->once())->method('handle')->with($message);

        $registry = new EntityIndexerRegistry([$indexer1, $this->indexerMock2], $this->messageBusMock, $this->dispatcherMock, $metricsInstrumentor);
        $registry->__invoke($message);
    }

    public function testRefreshResetsWorkingStateWhenIndexerThrows(): void
    {
        $event = static::createStub(EntityWrittenContainerEvent::class);
        $event->method('getContext')->willReturn(Context::createDefaultContext());

        $calls = 0;
        $indexer = $this->createMock(EntityIndexer::class);
        $indexer->expects($this->exactly(2))
            ->method('update')
            ->with($event)
            ->willReturnCallback(static function () use (&$calls): ?EntityIndexingMessage {
                if (++$calls === 1) {
                    throw new \RuntimeException('indexer failed');
                }

                return null;
            });

        $registry = new EntityIndexerRegistry([$indexer], $this->messageBusMock, $this->dispatcherMock, $this->instrumentorStub);

        try {
            $registry->refresh($event);
            static::fail('expected the indexer exception to bubble up');
        } catch (\RuntimeException $e) {
            static::assertSame('indexer failed', $e->getMessage());
        }

        // the second refresh must reach the indexer again - the working flag was reset despite the exception,
        // otherwise all indexing stays silently disabled for the rest of the process
        $registry->refresh($event);
    }

    public function testAddOnliesAddsCorrectSkips(): void
    {
        $context = Context::createDefaultContext();
        $messageMock = $this->createMock(EntityIndexingMessage::class);

        $options = ['indexer1', 'indexer2', 'indexer3', 'indexer4', 'indexer5', 'indexer6'];
        $onlyIndexer = new ArrayEntity(['onlies' => ['indexer1', 'indexer3', 'indexer4']]);
        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY, $onlyIndexer);

        $messageMock->expects($this->once())
            ->method('setSkip')
            ->with(static::callback(
                static function (array $skips) {
                    sort($skips);

                    return $skips === ['indexer2', 'indexer5', 'indexer6'];
                }
            ));

        EntityIndexerRegistry::addOnlyAllowedIndexers($messageMock, $options, $context);
    }
}
