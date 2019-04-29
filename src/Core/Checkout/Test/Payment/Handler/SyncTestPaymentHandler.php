<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class SyncTestPaymentHandler implements SynchronousPaymentHandlerInterface
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

    public function pay(SyncPaymentTransactionStruct $transaction, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();
        $order = $transaction->getOrder();

        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            throw new SyncPaymentProcessException($transactionId, 'lineItems is null');
        }

        $customer = $order->getOrderCustomer()->getCustomer();
        if ($customer === null) {
            throw new SyncPaymentProcessException($transactionId, 'customer is null');
        }

        $context = $salesChannelContext->getContext();
        $completeStateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PAID,
            $context
        )->getId();

        $transactionUpdate = [
            'id' => $transactionId,
            'stateId' => $completeStateId,
        ];

        $this->transactionRepository->update([$transactionUpdate], $context);
    }
}
