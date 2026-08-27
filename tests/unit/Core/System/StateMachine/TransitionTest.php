<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Transition::class)]
class TransitionTest extends TestCase
{
    public function testItExposesTheValuesItWasBuiltWith(): void
    {
        $entityId = Uuid::randomHex();

        $transition = new Transition(
            entityName: 'order_transaction',
            entityId: $entityId,
            transitionName: 'fail',
            stateFieldName: 'stateId',
            internalComment: 'the payment handler failed',
            skipIfInStates: ['paid', 'authorized'],
        );

        static::assertSame('order_transaction', $transition->getEntityName());
        static::assertSame($entityId, $transition->getEntityId());
        static::assertSame('fail', $transition->getTransitionName());
        static::assertSame('stateId', $transition->getStateFieldName());
        static::assertSame('the payment handler failed', $transition->getInternalComment());
        static::assertSame(['paid', 'authorized'], $transition->getSkipIfInStates());
    }

    public function testTheOptionalValuesDefaultToNothing(): void
    {
        $transition = new Transition('order_transaction', Uuid::randomHex(), 'paid', 'stateId');

        static::assertNull($transition->getInternalComment());
        static::assertSame([], $transition->getSkipIfInStates());
    }
}
