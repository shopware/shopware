<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\StateMachineLocker;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\StateMachineTransitionResult;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(StateMachineRegistry::class)]
#[CoversClass(StateMachineTransitionResult::class)]
class StateMachineRegistryTest extends TestCase
{
    private int $transactionalCalls = 0;

    public function testTransitionWritesHistoryAndUpdatesEntityInsideLock(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId', 'internal comment');
        $context = new Context(new AdminApiSource('user-id', 'integration-id'));
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher);

        $fixture->historyRepository->expects($this->once())
            ->method('create')
            ->with(
                [[
                    'stateMachineId' => $toPlace->getStateMachineId(),
                    'entityName' => 'order_transaction',
                    'fromStateId' => $fromPlace->getId(),
                    'toStateId' => $toPlace->getId(),
                    'transitionActionName' => 'paid',
                    'userId' => 'user-id',
                    'integrationId' => 'integration-id',
                    'referencedId' => $transition->getEntityId(),
                    'referencedVersionId' => $context->getVersionId(),
                    'internalComment' => 'internal comment',
                ]],
                $context
            );

        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $context);

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($fromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($toPlace, $stateMachineStates->get('toPlace'));
        static::assertCount(3, $dispatcher->events);
    }

    public function testTransitionDoesNotUpdateStateWhenHistoryWriteFails(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher);

        // The history entry is written first inside the transaction; if it fails, the state must not be
        // updated, so no entity-written events are dispatched for a state change that never commits.
        $fixture->historyRepository->method('create')
            ->willThrowException(new \RuntimeException('history write failed'));

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $this->expectExceptionObject(new \RuntimeException('history write failed'));

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionWritesHistoryAndStateInsideTransaction(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, new CollectingEventDispatcher());

        $fixture->historyRepository->expects($this->once())
            ->method('create');
        $fixture->entityRepository->expects($this->once())
            ->method('upsert');

        $fixture->registry->transition($transition, $context);

        // The history and state writes must be performed inside a single transaction.
        static::assertSame(1, $this->transactionalCalls);
    }

    public function testTransitionSkipsWritesAndEventsForUnnecessaryTransition(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $this->createState('open'), $fromPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher);

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($fromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($fromPlace, $stateMachineStates->get('toPlace'));
        static::assertSame([], $dispatcher->events);
    }

    public function testTransitionWithEmptyTransitionNameThrowsIllegalTransitionException(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), '', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, new CollectingEventDispatcher());

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $this->expectExceptionObject(new IllegalTransitionException($fromPlace->getId(), '', ['paid']));

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionCanForceDestinationStateByTechnicalName(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $context->addState(SetOrderStateAction::FORCE_TRANSITION);
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $toPlace);

        $fixture->historyRepository->expects($this->once())
            ->method('create');

        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $context);

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($fromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($toPlace, $stateMachineStates->get('toPlace'));
        static::assertCount(3, $dispatcher->events);
    }

    public function testTransitionUsesLockerAndDispatchesEventsForChangedState(): void
    {
        $transition = new Transition('order_transaction', 'transaction-id', 'paid', 'stateId', 'internal comment');
        $context = new Context(new AdminApiSource(null));
        $transitionResult = $this->createTransitionResult(true);
        $dispatcher = new CollectingEventDispatcher();
        $locker = $this->createMock(StateMachineLocker::class);

        $locker->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static function (Transition $passedTransition, Context $passedContext, \Closure $closure) use ($transition, $context, $transitionResult): StateMachineTransitionResult {
                static::assertSame($transition, $passedTransition);
                static::assertSame($context, $passedContext);
                static::assertSame(Context::SYSTEM_SCOPE, $passedContext->getScope());

                return $transitionResult;
            });

        $registry = $this->createRegistry($locker, $dispatcher);

        $stateMachineStates = $registry->transition($transition, $context);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertSame($transitionResult->stateMachineStates, $stateMachineStates);
        static::assertCount(3, $dispatcher->events);

        static::assertInstanceOf(StateMachineTransitionEvent::class, $dispatcher->events[0]['event']);
        static::assertNull($dispatcher->events[0]['name']);
        static::assertSame('order_transaction', $dispatcher->events[0]['event']->getEntityName());
        static::assertSame('transaction-id', $dispatcher->events[0]['event']->getEntityId());
        static::assertSame('internal comment', $dispatcher->events[0]['event']->getInternalComment());
        static::assertSame($transitionResult->fromPlace, $dispatcher->events[0]['event']->getFromPlace());
        static::assertSame($transitionResult->toPlace, $dispatcher->events[0]['event']->getToPlace());

        static::assertInstanceOf(StateMachineStateChangeEvent::class, $dispatcher->events[1]['event']);
        static::assertSame('state_machine.order_transaction.state_changed', $dispatcher->events[1]['name']);
        static::assertSame(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE, $dispatcher->events[1]['event']->getTransitionSide());
        static::assertSame($transitionResult->fromPlace, $dispatcher->events[1]['event']->getPreviousState());
        static::assertSame($transitionResult->toPlace, $dispatcher->events[1]['event']->getNextState());

        static::assertInstanceOf(StateMachineStateChangeEvent::class, $dispatcher->events[2]['event']);
        static::assertSame('state_machine.order_transaction.state_changed', $dispatcher->events[2]['name']);
        static::assertSame(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER, $dispatcher->events[2]['event']->getTransitionSide());
        static::assertSame($transitionResult->fromPlace, $dispatcher->events[2]['event']->getPreviousState());
        static::assertSame($transitionResult->toPlace, $dispatcher->events[2]['event']->getNextState());
    }

    public function testTransitionDoesNotDispatchEventsForUnchangedState(): void
    {
        $transition = new Transition('order_transaction', 'transaction-id', 'paid', 'stateId');
        $context = new Context(new AdminApiSource(null));
        $transitionResult = $this->createTransitionResult(false);
        $dispatcher = new CollectingEventDispatcher();
        $locker = $this->createMock(StateMachineLocker::class);

        $locker->expects($this->once())
            ->method('locked')
            ->with($transition, $context, static::isInstanceOf(\Closure::class))
            ->willReturn($transitionResult);

        $registry = $this->createRegistry($locker, $dispatcher);

        $stateMachineStates = $registry->transition($transition, $context);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertSame($transitionResult->stateMachineStates, $stateMachineStates);
        static::assertSame([], $dispatcher->events);
    }

    private function createRegistry(StateMachineLocker $locker, EventDispatcherInterface $dispatcher): StateMachineRegistry
    {
        return new StateMachineRegistry(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $dispatcher,
            $this->createMock(DefinitionInstanceRegistry::class),
            $locker,
            $this->createConnection()
        );
    }

    private function createConnection(): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(function (\Closure $func): mixed {
                ++$this->transactionalCalls;

                return $func();
            });

        return $connection;
    }

    private function createRegistryFixture(
        StateMachineEntity $stateMachine,
        StateMachineStateEntity $fromPlace,
        EventDispatcherInterface $dispatcher,
        ?StateMachineStateEntity $forcedToPlace = null
    ): StateMachineRegistryFixture {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<StateMachineCollection>&MockObject $stateMachineRepository */
        $stateMachineRepository = $this->createMock(EntityRepository::class);
        /** @var EntityRepository<StateMachineStateCollection>&MockObject $stateMachineStateRepository */
        $stateMachineStateRepository = $this->createMock(EntityRepository::class);
        /** @var EntityRepository<StateMachineHistoryCollection>&MockObject $historyRepository */
        $historyRepository = $this->createMock(EntityRepository::class);
        /** @var EntityRepository<EntityCollection<Entity>>&MockObject $entityRepository */
        $entityRepository = $this->createMock(EntityRepository::class);
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition = new StateMachineRegistryTestEntityDefinition();
        $definition->compile($definitionRegistry);
        $locker = $this->createMock(StateMachineLocker::class);

        $stateMachineRepository->method('search')
            ->willReturn($this->createSearchResult('state_machine', new StateMachineCollection([$stateMachine]), $context));

        $stateMachineStateRepository->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($fromPlace, $forcedToPlace): EntitySearchResult {
                $state = $criteria->getIds() === [] && $forcedToPlace !== null ? $forcedToPlace : $fromPlace;

                return $this->createSearchResult('state_machine_state', new StateMachineStateCollection([$state]), $context);
            });

        $entityRepository->method('search')
            ->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->createSearchResult(
                'order_transaction',
                new EntityCollection([new ArrayEntity(['id' => $criteria->getIds()[0], 'stateId' => $fromPlace->getId()])]),
                $context
            ));

        $definitionRegistry->method('getByEntityName')
            ->with('order_transaction')
            ->willReturn($definition);

        $definitionRegistry->method('getRepository')
            ->with('order_transaction')
            ->willReturn($entityRepository);

        $locker->method('locked')
            ->willReturnCallback(static fn (Transition $transition, Context $context, \Closure $closure): StateMachineTransitionResult => $closure());

        return new StateMachineRegistryFixture(
            new StateMachineRegistry(
                $stateMachineRepository,
                $stateMachineStateRepository,
                $historyRepository,
                $dispatcher,
                $definitionRegistry,
                $locker,
                $this->createConnection()
            ),
            $entityRepository,
            $historyRepository
        );
    }

    /**
     * @param list<StateMachineTransitionEntity> $transitions
     */
    private function createStateMachine(array $transitions): StateMachineEntity
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId(Uuid::randomHex());
        $stateMachine->setTechnicalName('order_transaction.state');
        $stateMachine->setTransitions(new StateMachineTransitionCollection($transitions));

        return $stateMachine;
    }

    private function createStateTransition(string $actionName, StateMachineStateEntity $fromPlace, StateMachineStateEntity $toPlace): StateMachineTransitionEntity
    {
        $transition = new StateMachineTransitionEntity();
        $transition->setId(Uuid::randomHex());
        $transition->setActionName($actionName);
        $transition->setFromStateId($fromPlace->getId());
        $transition->setFromStateMachineState($fromPlace);
        $transition->setToStateId($toPlace->getId());
        $transition->setToStateMachineState($toPlace);
        $transition->setStateMachineId($fromPlace->getStateMachineId());

        return $transition;
    }

    private function createState(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setStateMachineId(Uuid::randomHex());
        $state->setTechnicalName($technicalName);

        return $state;
    }

    /**
     * @param EntityCollection<covariant Entity> $collection
     *
     * @return EntitySearchResult<EntityCollection<covariant Entity>>
     */
    private function createSearchResult(string $entityName, EntityCollection $collection, Context $context): EntitySearchResult
    {
        return new EntitySearchResult($entityName, $collection->count(), $collection, null, new Criteria(), $context);
    }

    private function createTransitionResult(bool $hasTransitioned): StateMachineTransitionResult
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId('state-machine-id');
        $stateMachine->setTechnicalName('order_transaction.state');

        $fromPlace = new StateMachineStateEntity();
        $fromPlace->setId('from-place-id');
        $fromPlace->setStateMachineId($stateMachine->getId());
        $fromPlace->setTechnicalName('open');

        $toPlace = new StateMachineStateEntity();
        $toPlace->setId('to-place-id');
        $toPlace->setStateMachineId($stateMachine->getId());
        $toPlace->setTechnicalName('paid');

        $stateMachineStates = new StateMachineStateCollection();
        $stateMachineStates->set('fromPlace', $fromPlace);
        $stateMachineStates->set('toPlace', $hasTransitioned ? $toPlace : $fromPlace);

        return new StateMachineTransitionResult(
            $hasTransitioned,
            $stateMachineStates,
            $stateMachine,
            $fromPlace,
            $hasTransitioned ? $toPlace : $fromPlace,
        );
    }
}

/**
 * @internal
 */
class CollectingEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<array{event: object, name: string|null}>
     */
    public array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = ['event' => $event, 'name' => $eventName];

        return $event;
    }
}

/**
 * @internal
 */
class StateMachineRegistryFixture
{
    /**
     * @param EntityRepository<EntityCollection<Entity>>&MockObject $entityRepository
     * @param EntityRepository<StateMachineHistoryCollection>&MockObject $historyRepository
     */
    public function __construct(
        public readonly StateMachineRegistry $registry,
        public readonly EntityRepository&MockObject $entityRepository,
        public readonly EntityRepository&MockObject $historyRepository,
    ) {
    }
}

/**
 * @internal
 */
class StateMachineRegistryTestEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'order_transaction';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StateMachineStateField('state_id', 'stateId', 'order_transaction.state'),
        ]);
    }
}
