<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderTransactionCaptureStateHandler::class)]
class OrderTransactionCaptureStateHandlerTest extends TestCase
{
    protected OrderTransactionCaptureStateHandler $stateHandler;

    protected StateMachineRegistry&MockObject $machineRegistryMock;

    protected string $transactionId;

    protected function setUp(): void
    {
        $this->transactionId = Uuid::randomHex();
        $this->machineRegistryMock = $this->createMock(StateMachineRegistry::class);
        $this->stateHandler = new OrderTransactionCaptureStateHandler($this->machineRegistryMock);
    }

    public function testComplete(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_COMPLETE);
        $this->stateHandler->complete($this->transactionId, Context::createDefaultContext());
    }

    public function testReopen(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_REOPEN);
        $this->stateHandler->reopen($this->transactionId, Context::createDefaultContext());
    }

    public function testFail(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_FAIL);
        $this->stateHandler->fail($this->transactionId, Context::createDefaultContext());
    }

    protected function stateMachineRegistry(string $transitionName): void
    {
        $this->machineRegistryMock
            ->expects($this->once())
            ->method('transition')
            ->with(new Transition(
                OrderTransactionCaptureDefinition::ENTITY_NAME,
                $this->transactionId,
                $transitionName,
                'stateId'
            ));
    }
}
