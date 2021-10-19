<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Shopware\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Shopware\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FlowDispatcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?EntityRepositoryInterface $flowRepository;

    private ?EntityRepositoryInterface $customerRepository;

    private ?EventDispatcherInterface $dispatcher;

    private FlowActionTestSubscriber $flowActionTestSubscriber;

    private TestDataCollection $ids;

    private ?AbstractFlowLoader $flowLoader;

    protected function setUp(): void
    {
        $this->flowActionTestSubscriber = new FlowActionTestSubscriber();

        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->dispatcher = $this->getContainer()->get('event_dispatcher');

        $this->dispatcher->addSubscriber($this->flowActionTestSubscriber);

        $this->flowLoader = $this->getContainer()->get(FlowLoader::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->resetCachedFlows();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->dispatcher->removeSubscriber($this->flowActionTestSubscriber);
    }

    public function testAllEventsPassThrough(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestFlowBusinessEvent($context);

        $eventDispatcherMock = static::createMock(EventDispatcherInterface::class);
        $eventDispatcherMock->expects(static::exactly(1))
            ->method('dispatch')
            ->willReturn($event);

        $dispatcher = new FlowDispatcher(
            $eventDispatcherMock,
            $this->getContainer()->get(LoggerInterface::class)
        );

        $dispatcher->setContainer($this->getContainer()->get('service_container'));

        $dispatcher->dispatch($event, $event->getName());
    }

    public function testSingleEventActionIsDispatchedTrueCase(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId')]);
        $event = new TestFlowBusinessEvent($context);

        $this->createFlow(true);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    public function testEventSkipTrigger(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId')]);
        $event = new TestFlowBusinessEvent($context);
        $this->createFlow(true);

        $event->getContext()->addState(Context::SKIP_TRIGGER_FLOW);

        $this->dispatcher->dispatch($event);

        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);

        $event->getContext()->removeState(Context::SKIP_TRIGGER_FLOW);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
    }

    public function testSingleEventActionIsDispatchedFalseCase(): void
    {
        $context = Context::createDefaultContext();
        //rule id not matched
        $context->setRuleIds([$this->ids->create('ruleId2')]);
        $event = new TestFlowBusinessEvent($context);
        $this->createFlow(true, [], []);

        $this->dispatcher->dispatch($event);

        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
    }

    public function testEventActionWithConfig(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId')]);
        $event = new TestFlowBusinessEvent($context);

        $eventConfig = [
            'tagIds' => [
                $this->ids->get('tag_id') => 'test tag',
            ],
            'entity' => OrderDefinition::ENTITY_NAME,
        ];

        $this->createFlow(true);

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
        static::assertEquals($eventConfig, $this->flowActionTestSubscriber->lastActionConfig);
    }

    public function testInactiveEventActionIsDispatched(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId')]);
        $event = new TestFlowBusinessEvent($context);

        $this->createFlow(false);

        $this->dispatcher->dispatch($event);

        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_next'] ?? 0);
    }

    public function testSequencePriority(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId'), $this->ids->create('ruleId2')]);
        $event = new TestFlowBusinessEvent($context);
        $sequenceId = Uuid::randomHex();

        $this->createFlow(
            true,
            [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $this->ids->create('ruleId2'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'displayGroup' => 2,
                    'rule' => [
                        'id' => $this->ids->create('ruleId2'),
                        'name' => 'Test rule',
                        'priority' => 10,
                        'conditions' => [['type' => (new AlwaysValidRule())->getName()]],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_next',
                    'displayGroup' => 2,
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id4') => 'test tag4',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_next',
                    'displayGroup' => 2,
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id5') => 'test tag5',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 2,
                    'trueCase' => true,
                ],
            ]
        );

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
        static::assertEquals(2, $this->flowActionTestSubscriber->actions['unit_test_action_next'] ?? 0);
        static::assertEquals($this->ids->get('tag_id5'), array_key_first($this->flowActionTestSubscriber->lastActionConfig['tagIds']));
    }

    public function testFlowPriority(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId'), $this->ids->create('ruleId2')]);
        $event = new TestFlowBusinessEvent($context);
        $sequenceId = Uuid::randomHex();

        $this->createFlow(
            true,
            [],
            [[
                'name' => 'Trigger test',
                'eventName' => TestFlowBusinessEvent::EVENT_NAME,
                'priority' => 1,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $sequenceId,
                        'parentId' => null,
                        'ruleId' => $this->ids->get('ruleId'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'id' => $this->ids->get('ruleId'),
                            'name' => 'Test rule',
                            'priority' => 1,
                            'conditions' => [
                                ['type' => (new AlwaysValidRule())->getName()],
                            ],
                        ],
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $sequenceId,
                        'ruleId' => null,
                        'actionName' => 'unit_test_action_true',
                        'config' => [
                            'tagIds' => [
                                $this->ids->get('tag_id6') => 'test tag',
                            ],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $sequenceId,
                        'ruleId' => null,
                        'actionName' => 'unit_test_action_next',
                        'config' => [
                            'tagIds' => [
                                $this->ids->get('tag_id7') => 'test tag2',
                            ],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 2,
                        'trueCase' => true,
                    ],
                ],
            ]]
        );

        $this->dispatcher->dispatch($event);

        static::assertEquals(2, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_next'] ?? 0);
        static::assertEquals($this->ids->get('tag_id7'), array_key_first($this->flowActionTestSubscriber->lastActionConfig['tagIds']));
    }

    public function testStopFlowAction(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds([$this->ids->create('ruleId'), $this->ids->create('ruleId2')]);
        $event = new TestFlowBusinessEvent($context);
        $sequenceId = Uuid::randomHex();

        $this->createFlow(
            true,
            [],
            [[
                'name' => 'Trigger test',
                'eventName' => TestFlowBusinessEvent::EVENT_NAME,
                'priority' => 20,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $sequenceId,
                        'parentId' => null,
                        'ruleId' => $this->ids->create('ruleId'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'id' => $this->ids->create('ruleId'),
                            'name' => 'Test rule',
                            'priority' => 1,
                            'conditions' => [
                                ['type' => (new AlwaysValidRule())->getName()],
                            ],
                        ],
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $sequenceId,
                        'ruleId' => null,
                        'actionName' => StopFlowAction::getName(),
                        'config' => [
                            'tagIds' => [
                                $this->ids->get('tag_id6') => 'test tag',
                            ],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'parentId' => $sequenceId,
                        'ruleId' => null,
                        'actionName' => '2nd_unit_test_action',
                        'config' => [
                            'tagIds' => [
                                $this->ids->get('tag_id7') => 'test tag2',
                            ],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 2,
                        'trueCase' => true,
                    ],
                ],
            ]]
        );

        $this->dispatcher->dispatch($event);

        static::assertEquals(1, $this->flowActionTestSubscriber->actions['unit_test_action_true'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_false'] ?? 0);
        static::assertEquals(0, $this->flowActionTestSubscriber->actions['unit_test_action_next'] ?? 0);
        static::assertEquals($this->ids->get('tag_id'), array_key_first($this->flowActionTestSubscriber->lastActionConfig['tagIds']));
    }

    private function createFlow(bool $isActive, array $additionSequence = [], array $additionFlow = []): void
    {
        $sequenceId = Uuid::randomHex();

        $this->flowRepository->create(array_merge([[
            'name' => 'Create Order',
            'eventName' => TestFlowBusinessEvent::EVENT_NAME,
            'priority' => 10,
            'active' => $isActive,
            'sequences' => array_merge([
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $this->ids->create('ruleId'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $this->ids->create('ruleId'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_true',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id') => 'test tag',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => 'unit_test_action_false',
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id2') => 'test tag2',
                        ],
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 2,
                    'trueCase' => false,
                ],
            ], $additionSequence),
        ],
        ], $additionFlow), Context::createDefaultContext());
    }

    private function resetCachedFlows(): void
    {
        $class = new \ReflectionClass($this->flowLoader);

        if ($class->hasProperty('flows')) {
            $class = new \ReflectionClass($this->flowLoader);
            $property = $class->getProperty('flows');
            $property->setAccessible(true);
            $property->setValue(
                $this->flowLoader,
                []
            );
        }
    }
}
