<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
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
#[Package('checkout')]
#[CoversClass(StateMachineRegistry::class)]
#[CoversClass(StateMachineTransitionResult::class)]
class StateMachineRegistryTest extends TestCase
{
    private int $transactionalCalls = 0;

    public function testLiveTransitionWritesHistoryAndStateInSingleBatch(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId', 'internal comment');
        $context = new Context(new AdminApiSource('user-id', 'integration-id'));
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        $fixture->historyRepository->expects($this->never())
            ->method('create');
        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $fixture->entityWriter->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, WriteContext $writeContext) use ($context, $transition, $fromPlace, $toPlace, $dispatcher): WriteResult {
                static::assertSame($context, $writeContext->getContext());
                static::assertSame([], $dispatcher->events);
                static::assertCount(2, $operations);

                [$historyOperation, $stateOperation] = $operations;
                static::assertInstanceOf(SyncOperation::class, $historyOperation);
                static::assertInstanceOf(SyncOperation::class, $stateOperation);
                static::assertSame('state-machine-history', $historyOperation->getKey());
                static::assertSame(StateMachineHistoryDefinition::ENTITY_NAME, $historyOperation->getEntity());
                static::assertSame(SyncOperation::ACTION_UPSERT, $historyOperation->getAction());

                $historyPayload = $historyOperation->getPayload();
                static::assertCount(1, $historyPayload);
                $history = $historyPayload[0];
                static::assertIsArray($history);
                static::assertArrayHasKey('id', $history);
                static::assertIsString($history['id']);
                static::assertTrue(Uuid::isValid($history['id']));
                $historyId = $history['id'];
                unset($history['id']);

                static::assertSame([
                    'stateMachineId' => $toPlace->getStateMachineId(),
                    'entityName' => 'order_transaction',
                    'fromStateId' => $fromPlace->getId(),
                    'toStateId' => $toPlace->getId(),
                    'transitionActionName' => 'paid',
                    'userId' => 'user-id',
                    'integrationId' => 'integration-id',
                    'sourceType' => 'admin-api',
                    'referencedId' => $transition->getEntityId(),
                    'referencedVersionId' => $context->getVersionId(),
                    'internalComment' => 'internal comment',
                ], $history);

                static::assertSame('state-machine-state', $stateOperation->getKey());
                static::assertSame('order_transaction', $stateOperation->getEntity());
                static::assertSame(SyncOperation::ACTION_UPSERT, $stateOperation->getAction());
                static::assertSame([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $stateOperation->getPayload());

                return new WriteResult([], [], [
                    StateMachineHistoryDefinition::ENTITY_NAME => [
                        new EntityWriteResult($historyId, $history, StateMachineHistoryDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                    ],
                    'order_transaction' => [
                        new EntityWriteResult($transition->getEntityId(), ['stateId' => $toPlace->getId()], 'order_transaction', EntityWriteResult::OPERATION_UPDATE),
                    ],
                ]);
            });

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($fromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($toPlace, $stateMachineStates->get('toPlace'));
        static::assertSame(0, $this->transactionalCalls);
        static::assertCount(5, $dispatcher->events);

        $historyEvent = $dispatcher->events[0]['event'];
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $historyEvent);
        static::assertNotNull($historyEvent->getEventByEntityName(StateMachineHistoryDefinition::ENTITY_NAME));

        $stateEvent = $dispatcher->events[1]['event'];
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $stateEvent);
        static::assertNotNull($stateEvent->getEventByEntityName('order_transaction'));
        static::assertInstanceOf(StateMachineTransitionEvent::class, $dispatcher->events[2]['event']);
    }

    #[DataProvider('transitionSourceProvider')]
    public function testTransitionRecordsWhereTheStateChangeCameFrom(
        Context $context,
        ?string $expectedUserId,
        ?string $expectedIntegrationId,
        ?string $expectedSourceType
    ): void {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');
        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $fixture->entityWriter->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, WriteContext $writeContext) use ($context, $transition, $fromPlace, $toPlace, $expectedUserId, $expectedIntegrationId, $expectedSourceType): WriteResult {
                static::assertSame($context, $writeContext->getContext());
                static::assertCount(2, $operations);

                $historyOperation = $operations[0];
                static::assertInstanceOf(SyncOperation::class, $historyOperation);
                $historyPayload = $historyOperation->getPayload();
                static::assertCount(1, $historyPayload);
                $history = $historyPayload[0];
                static::assertIsArray($history);
                unset($history['id']);

                static::assertSame([
                    'stateMachineId' => $toPlace->getStateMachineId(),
                    'entityName' => 'order_transaction',
                    'fromStateId' => $fromPlace->getId(),
                    'toStateId' => $toPlace->getId(),
                    'transitionActionName' => 'paid',
                    'userId' => $expectedUserId,
                    'integrationId' => $expectedIntegrationId,
                    'sourceType' => $expectedSourceType,
                    'referencedId' => $transition->getEntityId(),
                    'referencedVersionId' => $context->getVersionId(),
                    'internalComment' => null,
                ], $history);

                return new WriteResult([]);
            });

        $fixture->registry->transition($transition, $context);
    }

    public static function transitionSourceProvider(): \Generator
    {
        yield 'store-api request has no admin actor and is recorded as sales channel source' => [
            new Context(new SalesChannelApiSource('sales-channel-id')),
            null,
            null,
            'sales-channel',
        ];

        yield 'internal transition without a request is recorded as system source' => [
            new Context(new SystemSource()),
            null,
            null,
            'system',
        ];

        yield 'admin user acting in a sales channel context stays the admin actor' => [
            new Context(new AdminSalesChannelApiSource('sales-channel-id', new Context(new AdminApiSource('user-id')))),
            'user-id',
            null,
            'admin-api',
        ];

        yield 'custom context source contributes its own type' => [
            new Context(new StateMachineRegistryTestContextSource()),
            null,
            null,
            'custom-source',
        ];

        yield 'context source without a public type is recorded without source' => [
            new Context(new StateMachineRegistryTestTypelessContextSource()),
            null,
            null,
            null,
        ];
    }

    public function testLiveTransitionDoesNotDispatchEventsWhenBatchFails(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        $fixture->historyRepository->expects($this->never())
            ->method('create');
        $fixture->entityRepository->expects($this->never())
            ->method('upsert');
        $fixture->entityWriter->expects($this->once())
            ->method('sync')
            ->willThrowException(new \RuntimeException('batch write failed'));

        try {
            $fixture->registry->transition($transition, $context);
            static::fail('Expected the batch write to fail.');
        } catch (\RuntimeException $exception) {
            static::assertSame('batch write failed', $exception->getMessage());
        }

        static::assertSame([], $dispatcher->events);
    }

    public function testLiveTransitionPropagatesWrittenEventFailureAfterBatchSucceeds(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $dispatcher = new FailingWrittenEventDispatcher();
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        $fixture->entityWriter->expects($this->once())
            ->method('sync')
            ->willReturn(new WriteResult([], [], [
                StateMachineHistoryDefinition::ENTITY_NAME => [
                    new EntityWriteResult(Uuid::randomHex(), [], StateMachineHistoryDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                ],
                'order_transaction' => [
                    new EntityWriteResult($transition->getEntityId(), [], 'order_transaction', EntityWriteResult::OPERATION_UPDATE),
                ],
            ]));

        try {
            $fixture->registry->transition($transition, $context);
            static::fail('Expected the written-event listener to fail.');
        } catch (\RuntimeException $exception) {
            static::assertSame('written-event listener failed', $exception->getMessage());
        }

        static::assertCount(1, $dispatcher->events);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $dispatcher->events[0]['event']);
    }

    public function testVersionedTransitionUsesRepositoriesInsideTransaction(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());
        $fromPlace = $this->createState('open');
        $toPlace = $this->createState('paid');
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $toPlace),
        ]);
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, new CollectingEventDispatcher(), $entityRepository, $historyRepository);

        $fixture->entityWriter->expects($this->never())
            ->method('sync');
        $fixture->historyRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $payload) use ($context): bool {
                static::assertCount(1, $payload);
                static::assertSame($context->getVersionId(), $payload[0]['referencedVersionId']);

                return true;
            }), $context);
        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $context);

        $fixture->registry->transition($transition, $context);

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');
        $fixture->entityWriter->expects($this->never())
            ->method('sync');

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, new CollectingEventDispatcher(), $entityRepository, $historyRepository);

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');
        $fixture->entityWriter->expects($this->never())
            ->method('sync');

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository, $toPlace);

        $fixture->historyRepository->expects($this->never())
            ->method('create');
        $fixture->entityRepository->expects($this->never())
            ->method('upsert');
        $fixture->entityWriter->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function (array $operations, WriteContext $writeContext) use ($context, $transition, $toPlace): WriteResult {
                static::assertSame($context, $writeContext->getContext());
                static::assertCount(2, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[1]);
                static::assertSame([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $operations[1]->getPayload());

                return new WriteResult([]);
            });

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
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(DefinitionInstanceRegistry::class),
            $locker,
            $this->createConnection(),
            static::createStub(EntityWriterInterface::class),
        );
    }

    private function createConnection(): Connection&Stub
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(function (\Closure $func): mixed {
                ++$this->transactionalCalls;

                return $func();
            });

        return $connection;
    }

    /**
     * @param EntityRepository<EntityCollection<Entity>>&MockObject $entityRepository
     * @param EntityRepository<StateMachineHistoryCollection>&MockObject $historyRepository
     */
    private function createRegistryFixture(
        StateMachineEntity $stateMachine,
        StateMachineStateEntity $fromPlace,
        EventDispatcherInterface $dispatcher,
        EntityRepository&MockObject $entityRepository,
        EntityRepository&MockObject $historyRepository,
        ?StateMachineStateEntity $forcedToPlace = null
    ): StateMachineRegistryFixture {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<StateMachineCollection>&Stub $stateMachineRepository */
        $stateMachineRepository = static::createStub(EntityRepository::class);
        /** @var EntityRepository<StateMachineStateCollection>&Stub $stateMachineStateRepository */
        $stateMachineStateRepository = static::createStub(EntityRepository::class);
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definition = new StateMachineRegistryTestEntityDefinition();
        $definition->compile($definitionRegistry);
        $locker = static::createStub(StateMachineLocker::class);
        $entityWriter = $this->createMock(EntityWriterInterface::class);

        $stateMachineRepository->method('search')
            ->willReturn($this->createSearchResult('state_machine', new StateMachineCollection([$stateMachine]), $context));

        $stateMachineStateRepository->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($fromPlace, $forcedToPlace): EntitySearchResult {
                $state = $criteria->getIds() === [] && $forcedToPlace !== null ? $forcedToPlace : $fromPlace;

                return $this->createSearchResult('state_machine_state', new StateMachineStateCollection([$state]), $context);
            });

        $entityRepository->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($fromPlace): EntitySearchResult {
                $entity = new ArrayEntity(['id' => $criteria->getIds()[0], 'stateId' => $fromPlace->getId()]);
                $entity->internalSetEntityData('order_transaction', new FieldVisibility([]));

                return $this->createSearchResult('order_transaction', new EntityCollection([$entity]), $context);
            });

        $definitionRegistry->method('getByEntityName')
            ->willReturnMap([['order_transaction', $definition]]);

        $definitionRegistry->method('getRepository')
            ->willReturnMap([['order_transaction', $entityRepository]]);

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
                $this->createConnection(),
                $entityWriter,
            ),
            $entityRepository,
            $historyRepository,
            $entityWriter,
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
class FailingWrittenEventDispatcher extends CollectingEventDispatcher
{
    public function dispatch(object $event, ?string $eventName = null): object
    {
        parent::dispatch($event, $eventName);

        if ($event instanceof EntityWrittenContainerEvent) {
            throw new \RuntimeException('written-event listener failed');
        }

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
        public readonly EntityWriterInterface&MockObject $entityWriter,
    ) {
    }
}

/**
 * @internal
 */
class StateMachineRegistryTestContextSource implements ContextSource
{
    public string $type = 'custom-source';
}

/**
 * @internal
 */
class StateMachineRegistryTestTypelessContextSource implements ContextSource
{
    protected string $type = 'not-readable-from-outside';
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
