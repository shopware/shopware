<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StateMachineEntity::class)]
class StateMachineEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $stateMachine = new StateMachineEntity();

        $historyEntries = new StateMachineHistoryCollection();
        $transitions = new StateMachineTransitionCollection();
        $states = new StateMachineStateCollection();

        $stateMachine->setTechnicalName('order.state');
        $stateMachine->setName('Order state');
        $stateMachine->setHistoryEntries($historyEntries);
        $stateMachine->setTransitions($transitions);
        $stateMachine->setStates($states);
        $stateMachine->setInitialStateId('initial-state-id');

        static::assertSame('order.state', $stateMachine->getTechnicalName());
        static::assertSame('Order state', $stateMachine->getName());
        static::assertSame($historyEntries, $stateMachine->getHistoryEntries());
        static::assertSame($transitions, $stateMachine->getTransitions());
        static::assertSame($states, $stateMachine->getStates());
        static::assertSame('initial-state-id', $stateMachine->getInitialStateId());
    }

    public function testGetInitialStateReturnsNullWithoutStates(): void
    {
        static::assertNull((new StateMachineEntity())->getInitialState());
    }

    public function testGetInitialStateFindsTheStateMatchingTheInitialStateId(): void
    {
        $open = self::state('open-id');
        $inProgress = self::state('in-progress-id');

        $stateMachine = new StateMachineEntity();
        $stateMachine->setStates(new StateMachineStateCollection([$open, $inProgress]));
        $stateMachine->setInitialStateId('open-id');

        static::assertSame($open, $stateMachine->getInitialState());

        $stateMachine->setInitialStateId('unknown-id');
        static::assertNull($stateMachine->getInitialState());
    }

    private static function state(string $id): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier($id);
        $state->setId($id);

        return $state;
    }
}
