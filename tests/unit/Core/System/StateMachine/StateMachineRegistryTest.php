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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Core\System\StateMachine\StateMachineException;
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

    /**
     * @var list<array{insideTransaction: bool}>
     */
    private array $lockingReads = [];

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

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
                    'sourceType' => 'admin-api',
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

        $fixture->historyRepository->expects($this->once())
            ->method('create')
            ->with(
                [[
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
                ]],
                $context
            );

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        // The history is written first, so a failure there must not update the state.
        $fixture->historyRepository->expects($this->once())
            ->method('create')
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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, new CollectingEventDispatcher(), $entityRepository, $historyRepository);

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
        $entityRepository = $this->createMock(EntityRepository::class);
        $historyRepository = $this->createMock(EntityRepository::class);
        $fixture = $this->createRegistryFixture($stateMachine, $fromPlace, $dispatcher, $entityRepository, $historyRepository);

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($fromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($fromPlace, $stateMachineStates->get('toPlace'));
        static::assertSame([], $dispatcher->events);
    }

    public function testTheRowIsLockedInsideTheTransactionThatWritesTheNewState(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
        );

        $fixture->historyRepository->expects($this->once())->method('create');
        $fixture->entityRepository->expects($this->once())->method('upsert');

        $fixture->registry->transition($transition, $context);

        // A lock taken outside the writing transaction is released before the write and guards nothing.
        static::assertCount(1, $this->lockingReads);
        static::assertTrue(
            $this->lockingReads[0]['insideTransaction'],
            'the entity row must be locked inside the transaction that writes the new state'
        );
        static::assertSame(1, $this->transactionalCalls);
    }

    public function testTransitionUsesTheStateTheRowHasWhenItIsLocked(): void
    {
        // What the caller read before it asked for the transition
        $staleFromPlace = $this->createState('open');
        // What another process committed in the meantime
        $lockedFromPlace = $this->createState('unconfirmed');
        $toPlace = $this->createState('paid');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $lockedFromPlace, $toPlace),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $staleFromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedPlace: $lockedFromPlace,
        );

        $fixture->historyRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $payload) use ($lockedFromPlace, $toPlace): bool {
                static::assertSame($lockedFromPlace->getId(), $payload[0]['fromStateId']);
                static::assertSame($toPlace->getId(), $payload[0]['toStateId']);

                return true;
            }));

        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $context);

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($lockedFromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($toPlace, $stateMachineStates->get('toPlace'));
    }

    public function testTransitionResolvesTheDestinationThatBelongsToTheLockedState(): void
    {
        $staleFromPlace = $this->createState('open');
        $lockedFromPlace = $this->createState('authorized');
        $destinationFromOpen = $this->createState('paid');
        $destinationFromAuthorized = $this->createState('paid_partially');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $staleFromPlace, $destinationFromOpen),
            $this->createStateTransition('paid', $lockedFromPlace, $destinationFromAuthorized),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $staleFromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedPlace: $lockedFromPlace,
        );

        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $destinationFromAuthorized->getId()]], $context);

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($destinationFromAuthorized, $stateMachineStates->get('toPlace'));
    }

    public function testTransitionIsIllegalWhenTheLockedStateNoLongerAllowsIt(): void
    {
        $staleFromPlace = $this->createState('unconfirmed');
        $lockedFromPlace = $this->createState('paid');
        $toPlace = $this->createState('failed');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'fail', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('fail', $staleFromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $staleFromPlace,
            $dispatcher,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedPlace: $lockedFromPlace,
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $this->expectExceptionObject(new IllegalTransitionException($lockedFromPlace->getId(), 'fail', []));

        try {
            $fixture->registry->transition($transition, $context);
        } finally {
            static::assertSame([], $dispatcher->events);
        }
    }

    public function testTransitionIsSkippedForAStateTheCallerRuledOut(): void
    {
        $staleFromPlace = $this->createState('unconfirmed');
        $lockedFromPlace = $this->createState('authorized');
        $toPlace = $this->createState('failed');
        // 'authorized' may legally be failed, so only the caller's own list keeps the transition from happening
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'fail', 'stateId', null, ['paid', 'authorized']);
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('fail', $lockedFromPlace, $toPlace),
        ]);
        $dispatcher = new CollectingEventDispatcher();
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $staleFromPlace,
            $dispatcher,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedPlace: $lockedFromPlace,
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $fixture->entityRepository->expects($this->never())
            ->method('upsert');

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($lockedFromPlace, $stateMachineStates->get('fromPlace'));
        static::assertSame($lockedFromPlace, $stateMachineStates->get('toPlace'));
        static::assertSame([], $dispatcher->events);
    }

    public function testTransitionStillHappensForAStateOutsideTheCallersList(): void
    {
        $fromPlace = $this->createState('unconfirmed');
        $toPlace = $this->createState('failed');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'fail', 'stateId', null, ['paid', 'authorized']);
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('fail', $fromPlace, $toPlace),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
        );

        $fixture->entityRepository->expects($this->once())
            ->method('upsert')
            ->with([['id' => $transition->getEntityId(), 'stateId' => $toPlace->getId()]], $context);

        $stateMachineStates = $fixture->registry->transition($transition, $context);

        static::assertSame($toPlace, $stateMachineStates->get('toPlace'));
    }

    public function testTransitionThrowsWhenTheEntityRowDoesNotExist(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            entityRowExists: false,
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $this->expectExceptionObject(
            StateMachineException::stateMachineInvalidEntityId('order_transaction', $transition->getEntityId())
        );

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionThrowsWhenTheEntityHasNoStorageAwarePrimaryKey(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            definition: new StateMachineRegistryTestVersionOnlyPrimaryKeyDefinition(),
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $this->expectExceptionObject(
            StateMachineException::stateMachineInvalidEntityId('order_transaction', $transition->getEntityId())
        );

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionThrowsWhenTheStateColumnIsEmpty(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedStateColumn: '',
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $this->expectExceptionObject(StateMachineException::stateMachineInvalidStateField('stateId'));

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionThrowsWhenTheLockedStateIsUnknown(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            lockedStateColumn: Uuid::fromHexToBytes(Uuid::randomHex()),
        );

        $fixture->historyRepository->expects($this->never())
            ->method('create');

        $this->expectExceptionObject(StateMachineException::stateMachineInvalidStateField('stateId'));

        $fixture->registry->transition($transition, $context);
    }

    public function testTransitionFallsBackToAnUnlockedReadForAnInheritedStateField(): void
    {
        $fromPlace = $this->createState('open');
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');
        $context = Context::createDefaultContext();
        $stateMachine = $this->createStateMachine([
            $this->createStateTransition('paid', $fromPlace, $this->createState('paid')),
        ]);
        $fixture = $this->createRegistryFixture(
            $stateMachine,
            $fromPlace,
            new CollectingEventDispatcher(),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            definition: new StateMachineRegistryTestInheritedStateEntityDefinition(),
        );

        $fixture->historyRepository->expects($this->once())->method('create');
        $fixture->entityRepository->expects($this->once())->method('upsert');

        $fixture->registry->transition($transition, $context);

        // An inherited state can come from the parent row, so it keeps the unlocked read.
        static::assertSame([], $this->lockingReads);
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
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $dispatcher,
            static::createStub(DefinitionInstanceRegistry::class),
            $locker,
            $this->createConnection()
        );
    }

    private function createConnection(string|false $lockedStateColumn = false): Connection&Stub
    {
        $insideTransaction = false;

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(function (\Closure $func) use (&$insideTransaction): mixed {
                ++$this->transactionalCalls;
                $insideTransaction = true;

                try {
                    return $func();
                } finally {
                    $insideTransaction = false;
                }
            });

        // Stands in for the locking read and records whether the transaction was already open when it ran.
        // False is how the database reports a missing row. StateMachineTransitionLockTest covers the real query.
        $connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $parameters) use ($lockedStateColumn, &$insideTransaction): string|false {
                $this->lockingReads[] = ['insideTransaction' => $insideTransaction];

                return $lockedStateColumn;
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
        ?StateMachineStateEntity $forcedToPlace = null,
        ?StateMachineStateEntity $lockedPlace = null,
        ?EntityDefinition $definition = null,
        bool $entityRowExists = true,
        string|false|null $lockedStateColumn = null
    ): StateMachineRegistryFixture {
        // The state the row carries when the transition locks it, which is not necessarily the one the caller saw.
        $lockedPlace ??= $fromPlace;
        $context = Context::createDefaultContext();
        /** @var EntityRepository<StateMachineCollection>&Stub $stateMachineRepository */
        $stateMachineRepository = static::createStub(EntityRepository::class);
        /** @var EntityRepository<StateMachineStateCollection>&Stub $stateMachineStateRepository */
        $stateMachineStateRepository = static::createStub(EntityRepository::class);
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definition ??= new StateMachineRegistryTestEntityDefinition();
        $definition->compile($definitionRegistry);
        $locker = static::createStub(StateMachineLocker::class);

        $stateMachineRepository->method('search')
            ->willReturn($this->createSearchResult('state_machine', new StateMachineCollection([$stateMachine]), $context));

        $knownStates = array_filter([$fromPlace, $lockedPlace, $forcedToPlace]);

        $stateMachineStateRepository->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($knownStates, $forcedToPlace): EntitySearchResult {
                // The forced-transition lookup searches by technical name instead of by id
                $states = $criteria->getIds() === []
                    ? array_filter([$forcedToPlace])
                    : array_filter($knownStates, static fn (StateMachineStateEntity $state) => \in_array($state->getId(), $criteria->getIds(), true));

                return $this->createSearchResult('state_machine_state', new StateMachineStateCollection($states), $context);
            });

        $entityRepository->method('search')
            ->willReturnCallback(fn (Criteria $criteria, Context $context): EntitySearchResult => $this->createSearchResult(
                'order_transaction',
                new EntityCollection([new ArrayEntity(['id' => $criteria->getIds()[0], 'stateId' => $fromPlace->getId()])]),
                $context
            ));

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
                $this->createConnection(
                    $lockedStateColumn ?? ($entityRowExists ? Uuid::fromHexToBytes($lockedPlace->getId()) : false)
                )
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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new PrimaryKey(), new Required()),
            new StateMachineStateField('state_id', 'stateId', 'order_transaction.state'),
        ]);
    }
}

/**
 * @internal
 */
class StateMachineRegistryTestVersionOnlyPrimaryKeyDefinition extends StateMachineRegistryTestEntityDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new VersionField())->addFlags(new PrimaryKey(), new Required()),
            new StateMachineStateField('state_id', 'stateId', 'order_transaction.state'),
        ]);
    }
}

/**
 * @internal
 */
class StateMachineRegistryTestInheritedStateEntityDefinition extends StateMachineRegistryTestEntityDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StateMachineStateField('state_id', 'stateId', 'order_transaction.state'))->addFlags(new Inherited()),
        ]);
    }
}
