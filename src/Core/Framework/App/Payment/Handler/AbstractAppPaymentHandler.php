<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Handler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\Payment\Payload\PayloadService;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractAppPaymentHandler
{
    protected OrderTransactionStateHandler $transactionStateHandler;

    protected StateMachineRegistry $stateMachineRegistry;

    protected PayloadService $payloadService;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        StateMachineRegistry $stateMachineRegistry,
        PayloadService $payloadService
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->payloadService = $payloadService;
    }

    protected function getAppPaymentMethod(OrderTransactionEntity $orderTransaction): AppPaymentMethodEntity
    {
        $paymentMethod = $orderTransaction->getPaymentMethod();
        if ($paymentMethod === null) {
            throw new AsyncPaymentProcessException($orderTransaction->getId(), 'Loaded data invalid');
        }

        $appPaymentMethod = $paymentMethod->getAppPaymentMethod();
        if ($appPaymentMethod === null) {
            throw new AsyncPaymentProcessException($orderTransaction->getId(), 'Loaded data invalid');
        }

        return $appPaymentMethod;
    }
}
