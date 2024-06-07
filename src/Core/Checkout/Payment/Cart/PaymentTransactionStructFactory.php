<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class PaymentTransactionStructFactory extends AbstractPaymentTransactionStructFactory
{
    public function getDecorated(): AbstractPaymentTransactionStructFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function sync(OrderTransactionEntity $orderTransaction, OrderEntity $order): SyncPaymentTransactionStruct
    {
        return new SyncPaymentTransactionStruct($orderTransaction, $order);
    }

    public function async(OrderTransactionEntity $orderTransaction, OrderEntity $order, string $returnUrl): AsyncPaymentTransactionStruct
    {
        return new AsyncPaymentTransactionStruct($orderTransaction, $order, $returnUrl);
    }

    public function prepared(OrderTransactionEntity $orderTransaction, OrderEntity $order): PreparedPaymentTransactionStruct
    {
        return new PreparedPaymentTransactionStruct($orderTransaction, $order);
    }

    public function recurring(OrderTransactionEntity $orderTransaction, OrderEntity $order): RecurringPaymentTransactionStruct
    {
        return new RecurringPaymentTransactionStruct($orderTransaction, $order);
    }

    public function build(string $orderTransactionId, Context $context, ?string $returnUrl = null): PaymentTransactionStruct
    {
        return new PaymentTransactionStruct($orderTransactionId, $returnUrl);
    }

    public function refund(string $refundId, string $orderTransactionId): RefundPaymentTransactionStruct
    {
        return new RefundPaymentTransactionStruct($refundId, $orderTransactionId);
    }
}
