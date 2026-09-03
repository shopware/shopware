<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StateMachineStateChangeEvent::class)]
class StateMachineStateChangeEventTest extends TestCase
{
    public function testEnterTransitionsUseTheNextStateName(): void
    {
        $event = self::event(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);

        static::assertSame('in_progress', $event->getStateName());
        static::assertSame('state_enter.order.state.in_progress', $event->getStateEventName());
    }

    public function testLeaveTransitionsUseThePreviousStateName(): void
    {
        $event = self::event(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE);

        static::assertSame('open', $event->getStateName());
        static::assertSame('state_leave.order.state.open', $event->getStateEventName());
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $context = Context::createDefaultContext();
        $transition = new Transition('order', 'order-id', 'process', 'stateId');
        $stateMachine = self::stateMachine();
        $previousState = self::state('open');
        $nextState = self::state('in_progress');
        $recipients = new MailRecipientStruct(['admin@example.com' => 'Admin']);

        $event = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            $transition,
            $stateMachine,
            $previousState,
            $nextState,
            $recipients
        );

        static::assertSame('state_machine.order.state_changed', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($transition, $event->getTransition());
        static::assertSame($stateMachine, $event->getStateMachine());
        static::assertSame($previousState, $event->getPreviousState());
        static::assertSame($nextState, $event->getNextState());
        static::assertSame(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER, $event->getTransitionSide());
        static::assertSame($recipients, $event->getMailRecipientStruct());
    }

    private static function event(string $side): StateMachineStateChangeEvent
    {
        return new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            $side,
            new Transition('order', 'order-id', 'process', 'stateId'),
            self::stateMachine(),
            self::state('open'),
            self::state('in_progress'),
        );
    }

    private static function stateMachine(): StateMachineEntity
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName('order.state');

        return $stateMachine;
    }

    private static function state(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
