<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryEndEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryPartialResult;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryStartEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing\Fixture\TestIndexer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexerRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var IndexerRegistry
     */
    private $indexer;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Event[]
     */
    private $events = [];

    /**
     * @var callable
     */
    private $callbackFn;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->indexer = new IndexerRegistry([new EmptyIndexer()], $this->eventDispatcher);
        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[\get_class($event)] = $event;
        };
    }

    public function tearDown(): void
    {
        $this->eventDispatcher->removeListener(IndexerRegistryStartEvent::class, $this->callbackFn);
        $this->eventDispatcher->removeListener(IndexerRegistryEndEvent::class, $this->callbackFn);
    }

    public function testPartialCanBeExecuted(): void
    {
        $lastId = new IndexerRegistryPartialResult(null, null);
        $timestamp = new \DateTime();

        $executed = [];

        while ($lastId = $this->indexer->partial($lastId->getIndexer(), $lastId->getOffset(), $timestamp)) {
            $key = md5($lastId->getIndexer() . json_encode($lastId->getOffset()));

            if (isset($executed[$key])) {
                static::fail('Same iteration executed twice: ' . $lastId->getIndexer() . ' - ' . json_encode($lastId->getOffset()));
            }

            $executed[$key] = true;
        }

        static::assertNotEmpty($executed);
    }

    public function testPreIndexEventIsDispatchedOnIndex(): void
    {
        $this->eventDispatcher->addListener(IndexerRegistryStartEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexerRegistryStartEvent::class, $this->events, 'IndexStartEvent was dispatched but should not yet.');

        $this->indexer->index(new \DateTimeImmutable());

        static::assertArrayHasKey(IndexerRegistryStartEvent::class, $this->events, 'IndexStartEvent was not dispatched.');
        /** @var IndexerRegistryStartEvent $indexerRegistryStartEvent */
        $indexerRegistryStartEvent = $this->events[IndexerRegistryStartEvent::class];
        static::assertInstanceOf(IndexerRegistryStartEvent::class, $indexerRegistryStartEvent);
        static::assertNull($indexerRegistryStartEvent->getContext());
    }

    public function testPostIndexEventIsDispatchedOnIndex(): void
    {
        $this->eventDispatcher->addListener(IndexerRegistryEndEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexerRegistryEndEvent::class, $this->events, 'IndexFinishedEvent was dispatched but should not yet.');

        $this->indexer->index(new \DateTimeImmutable());

        static::assertArrayHasKey(IndexerRegistryEndEvent::class, $this->events, 'IndexFinishedEvent was not dispatched.');
        /** @var IndexerRegistryEndEvent $indexerRegistryEndEvent */
        $indexerRegistryEndEvent = $this->events[IndexerRegistryEndEvent::class];
        static::assertInstanceOf(IndexerRegistryEndEvent::class, $indexerRegistryEndEvent);
        static::assertNull($indexerRegistryEndEvent->getContext());
    }

    public function testPreIndexEventIsDispatchedOnRefresh(): void
    {
        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $this->eventDispatcher->addListener(IndexerRegistryStartEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexerRegistryStartEvent::class, $this->events, 'IndexStartEvent was dispatched but should not yet.');

        $this->indexer->refresh($refreshEvent);

        static::assertArrayHasKey(IndexerRegistryStartEvent::class, $this->events, 'IndexStartEvent was not dispatched.');
        /** @var IndexerRegistryStartEvent $indexerRegistryStartEvent */
        $indexerRegistryStartEvent = $this->events[IndexerRegistryStartEvent::class];
        static::assertInstanceOf(IndexerRegistryStartEvent::class, $indexerRegistryStartEvent);
        static::assertSame($context, $indexerRegistryStartEvent->getContext());
    }

    public function testPostIndexEventIsDispatchedOnRefresh(): void
    {
        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $this->eventDispatcher->addListener(IndexerRegistryEndEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexerRegistryEndEvent::class, $this->events, 'IndexFinishedEvent was dispatched but should not yet.');

        $this->indexer->refresh($refreshEvent);

        static::assertArrayHasKey(IndexerRegistryEndEvent::class, $this->events, 'IndexFinishedEvent was not dispatched.');
        /** @var IndexerRegistryEndEvent $indexerRegistryEndEvent */
        $indexerRegistryEndEvent = $this->events[IndexerRegistryEndEvent::class];
        static::assertInstanceOf(IndexerRegistryEndEvent::class, $indexerRegistryEndEvent);
        static::assertSame($context, $indexerRegistryEndEvent->getContext());
    }

    public function testLockWhileIndexing(): void
    {
        /** @var EventDispatcherInterface|MockObject $eventDispatcherMock */
        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();
        $indexer = new IndexerRegistry([], $eventDispatcherMock);
        $property = ReflectionHelper::getProperty(IndexerRegistry::class, 'indexer');

        $testIndexer = new TestIndexer($indexer);
        $property->setValue($indexer, [$testIndexer]);

        $indexer->index(new \DateTimeImmutable());

        static::assertSame(1, $testIndexer->getIndexCalls(), 'Indexer were called multiple times in a single run.');
    }

    public function testLockWhileRefreshing(): void
    {
        /** @var EventDispatcherInterface|MockObject $eventDispatcherMock */
        $eventDispatcherMock = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();
        $indexer = new IndexerRegistry([], $eventDispatcherMock);
        $property = ReflectionHelper::getProperty(IndexerRegistry::class, 'indexer');

        $testIndexer = new TestIndexer($indexer);
        $property->setValue($indexer, [$testIndexer]);

        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $indexer->refresh($refreshEvent);

        static::assertSame(1, $testIndexer->getRefreshCalls(), 'Indexer were called multiple times in a single run.');
    }
}

class EmptyIndexer implements IndexerInterface
{
    public function index(\DateTimeInterface $timestamp): void
    {
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        if ($lastId === null) {
            return ['123'];
        }

        return null;
    }

    public static function getName(): string
    {
        return self::class;
    }
}
