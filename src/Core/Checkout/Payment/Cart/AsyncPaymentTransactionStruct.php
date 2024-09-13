<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - will be removed, use PaymentTransactionStruct with new payment handlers instead
 */
#[Package('checkout')]
class AsyncPaymentTransactionStruct extends SyncPaymentTransactionStruct
{
    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        protected string $returnUrl,
        protected ?RecurringDataStruct $recurringData = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct with new payment handlers instead');

        parent::__construct($orderTransaction, $order, $recurringData);
    }

    public function getReturnUrl(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct with new payment handlers instead');

        return $this->returnUrl;
    }
}
