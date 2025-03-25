<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderTransaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
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
#[CoversClass(OrderTransactionStateHandler::class)]
class OrderTransactionStateHandlerTest extends TestCase
{
    protected OrderTransactionStateHandler $stateHandler;

    protected StateMachineRegistry&MockObject $machineRegistryMock;

    protected string $transactionId;

    protected function setUp(): void
    {
        $this->transactionId = Uuid::randomHex();
        $this->machineRegistryMock = $this->createMock(StateMachineRegistry::class);
        $this->stateHandler = new OrderTransactionStateHandler($this->machineRegistryMock);
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

    public function testProcess(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_DO_PAY);
        $this->stateHandler->process($this->transactionId, Context::createDefaultContext());
    }

    public function testProcessUnconfirmed(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_PROCESS_UNCONFIRMED);
        $this->stateHandler->processUnconfirmed($this->transactionId, Context::createDefaultContext());
    }

    public function testPaid(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_PAID);
        $this->stateHandler->paid($this->transactionId, Context::createDefaultContext());
    }

    public function testPayPartially(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_PAID_PARTIALLY);
        $this->stateHandler->payPartially($this->transactionId, Context::createDefaultContext());
    }

    public function testRefund(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_REFUND);
        $this->stateHandler->refund($this->transactionId, Context::createDefaultContext());
    }

    public function testRefundPartially(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_REFUND_PARTIALLY);
        $this->stateHandler->refundPartially($this->transactionId, Context::createDefaultContext());
    }

    public function testCancel(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_CANCEL);
        $this->stateHandler->cancel($this->transactionId, Context::createDefaultContext());
    }

    public function testRemind(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_REMIND);
        $this->stateHandler->remind($this->transactionId, Context::createDefaultContext());
    }

    public function testAuthorize(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_AUTHORIZE);
        $this->stateHandler->authorize($this->transactionId, Context::createDefaultContext());
    }

    public function testChargeback(): void
    {
        $this->stateMachineRegistry(StateMachineTransitionActions::ACTION_CHARGEBACK);
        $this->stateHandler->chargeback($this->transactionId, Context::createDefaultContext());
    }

    protected function stateMachineRegistry(string $transitionName): void
    {
        $this->machineRegistryMock
            ->expects($this->once())
            ->method('transition')
            ->with(new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $this->transactionId,
                $transitionName,
                'stateId'
            ));
    }
}
