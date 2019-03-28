<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\StateMachine\OrderTransactionStateMachine;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class InvoicePayment implements SynchronousPaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $transactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(SyncPaymentTransactionStruct $transaction, Context $context): void
    {
        $completeStateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStateMachine::NAME,
            OrderTransactionStateMachine::STATE_PAID,
            $context
        )->getId();

        $data = [
            'id' => $transaction->getOrderTransaction()->getId(),
            'stateId' => $completeStateId,
        ];

        $this->transactionRepository->update([$data], $context);
    }
}
