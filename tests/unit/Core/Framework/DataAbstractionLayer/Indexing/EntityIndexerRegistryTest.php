<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(EntityIndexerRegistry::class)]
class EntityIndexerRegistryTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBusMock;

    private EventDispatcherInterface&MockObject $dispatcherMock;

    private EntityIndexer&MockObject $indexerMock1;

    private EntityIndexer&MockObject $indexerMock2;

    private EntityIndexerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->dispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->indexerMock1 = $this->createMock(EntityIndexer::class);
        $this->indexerMock2 = $this->createMock(EntityIndexer::class);

        $indexers = [$this->indexerMock1, $this->indexerMock2];

        $this->registry = new EntityIndexerRegistry($indexers, $this->messageBusMock, $this->dispatcherMock);
    }

    public function testIndexSuccessful(): void
    {
        $this->dispatcherMock->expects(static::exactly(4))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof ProgressStartedEvent || $event instanceof ProgressFinishedEvent) {
                    return $event;
                }

                return null;
            });

        $this->registry->index(false);
    }

    public function testIndexSuccessfulFullEntity(): void
    {
        $fullEntityIndexerMessageMock = $this->createMock(FullEntityIndexerMessage::class);

        $skip = ['indexer1'];
        $only = ['indexer2'];

        $indexers = [$this->indexerMock1, $this->indexerMock2];

        $registryMock = $this->getMockBuilder(EntityIndexerRegistry::class)
            ->setConstructorArgs([$indexers, $this->messageBusMock, $this->dispatcherMock])
            ->onlyMethods(['index'])
            ->getMock();

        $registryMock->expects(static::once())
            ->method('index')
            ->with(true, $skip, $only);

        $fullEntityIndexerMessageMock->expects(static::once())
            ->method('getSkip')
            ->willReturn($skip);

        $fullEntityIndexerMessageMock->expects(static::once())
            ->method('getOnly')
            ->willReturn($only);

        $registryMock->__invoke($fullEntityIndexerMessageMock);
    }

    public function testIndexWithSkipAndOnlyParameters(): void
    {
        $skip = ['indexer1'];
        $only = ['indexer2'];

        $this->indexerMock1->method('getName')->willReturn('indexer1');
        $this->indexerMock2->method('getName')->willReturn('indexer2');

        $this->indexerMock1->expects(static::never())->method('iterate');
        $this->indexerMock2->expects(static::atLeastOnce())->method('iterate');

        $this->registry->index(false, $skip, $only);
    }

    public function testRefreshMethod(): void
    {
        $eventMock = $this->createMock(EntityWrittenContainerEvent::class);
        $context = Context::createDefaultContext();
        $skipEntity = new ArrayEntity(['skips' => ['skip1', 'skip2']]);
        $messageMock = $this->createMock(EntityIndexingMessage::class);

        $this->indexerMock1->method('getName')->willReturn('indexer1');
        $this->indexerMock2->method('getName')->willReturn('indexer2');

        $eventMock->expects(static::once())
            ->method('getContext')
            ->willReturn($context);

        $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, $skipEntity);

        $this->indexerMock1->expects(static::once())
            ->method('update')
            ->with($eventMock)
            ->willReturn($messageMock);

        $messageMock->expects(static::once())
            ->method('setIndexer')
            ->with('indexer1');
        $messageMock->expects(static::once())
            ->method('addSkip')
            ->with('skip1', 'skip2');

        $this->registry->refresh($eventMock);
    }
}
