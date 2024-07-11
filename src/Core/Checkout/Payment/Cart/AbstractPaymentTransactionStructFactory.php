<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * This factory is intended to be decorated in order to manipulate the structs that are used in the payment process ny the payment handlers
 */
#[Package('checkout')]
abstract class AbstractPaymentTransactionStructFactory
{
    abstract public function getDecorated(): AbstractPaymentTransactionStructFactory;

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `build` instead
     */
    abstract public function sync(OrderTransactionEntity $orderTransaction, OrderEntity $order): SyncPaymentTransactionStruct;

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `build` instead
     */
    abstract public function async(OrderTransactionEntity $orderTransaction, OrderEntity $order, string $returnUrl): AsyncPaymentTransactionStruct;

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `build` instead
     */
    abstract public function prepared(OrderTransactionEntity $orderTransaction, OrderEntity $order): PreparedPaymentTransactionStruct;

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `build` instead
     */
    abstract public function recurring(OrderTransactionEntity $orderTransaction, OrderEntity $order): RecurringPaymentTransactionStruct;

    /**
     * @deprecated tag:v6.7.0 - will be abstract, implementation is in `PaymentTransactionStructFactory`
     */
    public function build(string $orderTransactionId, Context $context, ?string $returnUrl = null): PaymentTransactionStruct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The method `build` needs to be implemented in the extending class.');

        return new PaymentTransactionStruct($orderTransactionId, $returnUrl);
    }

    /**
     * @deprecated tag:v6.7.0 - will be abstract, implementation is in `PaymentTransactionStructFactory`
     */
    public function refund(string $refundId, string $orderTransactionId): RefundPaymentTransactionStruct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The method `refund` needs to be implemented in the extending class.');

        return new RefundPaymentTransactionStruct($refundId, $orderTransactionId);
    }
}
