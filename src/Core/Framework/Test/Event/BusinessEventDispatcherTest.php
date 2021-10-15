<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Event\EventAction\EventActionCollection;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use FlowDispatcherTest instead.
 */
class BusinessEventDispatcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $eventActionRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var ActionTestSubscriber
     */
    private $testSubscriber;

    protected function setUp(): void
    {
        if (Feature::isActive('FEATURE_NEXT_17858')) {
            static::markTestSkipped('Business Event is deprecated since v6.4.6, flag FEATURE_NEXT_17858');
        }

        $this->testSubscriber = new ActionTestSubscriber();

        $this->eventActionRepository = $this->getContainer()->get('event_action.repository');
        $this->dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->dispatcher->addSubscriber($this->testSubscriber);
    }

    protected function tearDown(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17858')) {
            parent::tearDown();

            $this->dispatcher->removeSubscriber($this->testSubscriber);
        }
    }

    public function testAllEventsPassthru(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $eventDispatcherMock = static::createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturn($event);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('event_action', 0, new EventActionCollection(), null, new Criteria(), Context::createDefaultContext()));

        $container = static::createMock(DefinitionInstanceRegistry::class);
        $container->expects(static::once())
            ->method('getRepository')
            ->willReturn($repository);

        $dispatcher = new BusinessEventDispatcher(
            $eventDispatcherMock,
            $container,
            $this->getContainer()->get(EventActionDefinition::class)
        );
        $dispatcher->dispatch($event, $event->getName());
    }

    public function testSingleEventActionIsDispatched(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
        ]], $context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['unit_test_action'] ?? 0);
    }

    public function testInativeEventIsNotDispatched(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
            'active' => false,
        ]], $context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0);
        static::assertEquals(0, $this->testSubscriber->actions['unit_test_action'] ?? 0);
    }

    public function testNotMatchingRulesNotDispatched(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $id = Uuid::randomHex();
        $this->getContainer()->get('rule.repository')
            ->create([[
                'id' => $id,
                'name' => 'test',
                'priority' => 100,
            ]], Context::createDefaultContext());

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
            'rules' => [
                ['id' => $id],
            ],
        ]], $context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0);
        static::assertEquals(0, $this->testSubscriber->actions['unit_test_action'] ?? 0);
    }

    public function testMatchingRuleDispatched(): void
    {
        $id = Uuid::randomHex();
        $this->getContainer()->get('rule.repository')
            ->create([[
                'id' => $id,
                'name' => 'test',
                'priority' => 100,
            ]], Context::createDefaultContext());

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
            'rules' => [
                ['id' => $id],
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $context->setRuleIds([$id]);
        $event = new TestBusinessEvent($context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['unit_test_action'] ?? 0);
    }

    public function testOneMatchingRuleDispatched(): void
    {
        $ids = new TestDataCollection();

        $this->getContainer()->get('rule.repository')
            ->create([
                ['id' => $ids->create('rule-a'), 'name' => 'test', 'priority' => 100],
                ['id' => $ids->create('rule-b'), 'name' => 'test', 'priority' => 100],
            ], Context::createDefaultContext());

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
            'rules' => [
                ['id' => $ids->get('rule-a')],
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $context->setRuleIds([$ids->get('rule-a')]);
        $event = new TestBusinessEvent($context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['unit_test_action'] ?? 0);
    }

    public function testMultipleEventActionIsDispatched(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([
            [
                'eventName' => TestBusinessEvent::EVENT_NAME,
                'actionName' => 'unit_test_action',
            ],
            [
                'eventName' => TestBusinessEvent::EVENT_NAME,
                'actionName' => '2nd_unit_test_action',
            ],
        ], $context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0, 'Global action event should only be dispatched once');
        static::assertEquals(1, $this->testSubscriber->events[TestBusinessEvent::EVENT_NAME] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['unit_test_action'] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['2nd_unit_test_action'] ?? 0);
    }

    public function testEventActionWithEmptyConfigReturnsArray(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([[
            'eventName' => TestBusinessEvent::EVENT_NAME,
            'actionName' => 'unit_test_action',
        ]], $context);

        $this->dispatcher->dispatch($event);

        static::assertIsArray($this->testSubscriber->lastActionConfig);
        static::assertEmpty($this->testSubscriber->lastActionConfig);
    }

    public function testEventActionWithConfig(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $eventConfig = [
            'foo' => 'bar',
            'wusel' => 'dusel',
        ];

        $this->eventActionRepository->create([
            [
                'eventName' => TestBusinessEvent::EVENT_NAME,
                'actionName' => 'unit_test_action',
                'config' => $eventConfig,
            ],
        ], $context);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->testSubscriber->events[BusinessEvents::GLOBAL_EVENT] ?? 0, 'Global action event should only be dispatched once');
        static::assertEquals(1, $this->testSubscriber->events[TestBusinessEvent::EVENT_NAME] ?? 0);
        static::assertEquals(1, $this->testSubscriber->actions['unit_test_action'] ?? 0);

        static::assertEquals($eventConfig, $this->testSubscriber->lastActionConfig);
    }

    public function testEventPropagation(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([
            [
                'eventName' => TestBusinessEvent::EVENT_NAME,
                'actionName' => 'unit_test_action',
            ],
        ], $context);

        $eventDispatcherMock = static::createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(static::exactly(3))
            ->method('dispatch')
            ->willReturn($event);

        $dispatcher = new BusinessEventDispatcher(
            $eventDispatcherMock,
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get(EventActionDefinition::class)
        );

        $dispatcher->dispatch($event, $event->getName());
    }

    public function testEventPropagationStopped(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestBusinessEvent($context);

        $this->eventActionRepository->create([
            [
                'eventName' => TestBusinessEvent::EVENT_NAME,
                'actionName' => 'unit_test_action',
            ],
        ], $context);

        $eventDispatcherMock = static::createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(static::once())
            ->method('dispatch')
            ->willReturn($event);

        $dispatcher = new BusinessEventDispatcher(
            $eventDispatcherMock,
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get(EventActionDefinition::class)
        );

        $event->stopPropagation();
        $dispatcher->dispatch($event);
    }
}
