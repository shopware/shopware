<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
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
            $locker
        );
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
