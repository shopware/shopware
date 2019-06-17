<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexFinishedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexStartEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
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
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
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

        $this->indexer = $this->getContainer()->get(IndexerRegistry::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->events = [];

        $this->callbackFn = function (Event $event) {
            $this->events[get_class($event)] = $event;
        };
    }

    public function tearDown(): void
    {
        $this->eventDispatcher->removeListener(IndexStartEvent::class, $this->callbackFn);
        $this->eventDispatcher->removeListener(IndexFinishedEvent::class, $this->callbackFn);
    }

    public function testPreIndexEventIsDispatchedOnIndex(): void
    {
        $this->eventDispatcher->addListener(IndexStartEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexStartEvent::class, $this->events, 'IndexStartEvent was dispatched but should not yet.');

        $this->indexer->index(new \DateTimeImmutable());

        static::assertArrayHasKey(IndexStartEvent::class, $this->events, 'IndexStartEvent was not dispatched.');
        static::assertNull($this->events[IndexStartEvent::class]->getContext());
    }

    public function testPostIndexEventIsDispatchedOnIndex(): void
    {
        $this->eventDispatcher->addListener(IndexFinishedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexFinishedEvent::class, $this->events, 'IndexFinishedEvent was dispatched but should not yet.');

        $this->indexer->index(new \DateTimeImmutable());

        static::assertArrayHasKey(IndexFinishedEvent::class, $this->events, 'IndexFinishedEvent was not dispatched.');
        static::assertNull($this->events[IndexFinishedEvent::class]->getContext());
    }

    public function testPreIndexEventIsDispatchedOnRefresh(): void
    {
        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $this->eventDispatcher->addListener(IndexStartEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexStartEvent::class, $this->events, 'IndexStartEvent was dispatched but should not yet.');

        $this->indexer->refresh($refreshEvent);

        static::assertArrayHasKey(IndexStartEvent::class, $this->events, 'IndexStartEvent was not dispatched.');
        static::assertEquals($context, $this->events[IndexStartEvent::class]->getContext());
    }

    public function testPostIndexEventIsDispatchedOnRefresh(): void
    {
        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $this->eventDispatcher->addListener(IndexFinishedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(IndexFinishedEvent::class, $this->events, 'IndexFinishedEvent was dispatched but should not yet.');

        $this->indexer->refresh($refreshEvent);

        static::assertArrayHasKey(IndexFinishedEvent::class, $this->events, 'IndexFinishedEvent was not dispatched.');
        static::assertEquals($context, $this->events[IndexFinishedEvent::class]->getContext());
    }

    public function testLockWhileIndexing(): void
    {
        $indexer = new IndexerRegistry([], $this->getMockBuilder(EventDispatcherInterface::class)->getMock());
        $property = ReflectionHelper::getProperty(IndexerRegistry::class, 'indexer');

        $testIndexer = new TestIndexer($indexer);
        $property->setValue($indexer, [$testIndexer]);

        $indexer->index(new \DateTimeImmutable());

        static::assertEquals(1, $testIndexer->getIndexCalls(), 'Indexer were called multiple times in a single run.');
    }

    public function testLockWhileRefreshing(): void
    {
        $indexer = new IndexerRegistry([], $this->getMockBuilder(EventDispatcherInterface::class)->getMock());
        $property = ReflectionHelper::getProperty(IndexerRegistry::class, 'indexer');

        $testIndexer = new TestIndexer($indexer);
        $property->setValue($indexer, [$testIndexer]);

        $context = Context::createDefaultContext();
        $refreshEvent = new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);

        $indexer->refresh($refreshEvent);

        static::assertEquals(1, $testIndexer->getRefreshCalls(), 'Indexer were called multiple times in a single run.');
    }
}

class TestIndexer implements IndexerInterface
{
    /**
     * @var IndexerRegistry
     */
    private $indexer;

    private $indexCalls = 0;
    private $refreshCalls = 0;

    public function __construct(IndexerRegistry $indexer)
    {
        $this->indexer = $indexer;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        ++$this->indexCalls;
        $this->indexer->index($timestamp);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        ++$this->refreshCalls;
        $this->indexer->refresh($event);
    }

    public function getIndexCalls(): int
    {
        return $this->indexCalls;
    }

    public function getRefreshCalls(): int
    {
        return $this->refreshCalls;
    }
}
