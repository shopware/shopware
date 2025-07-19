<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

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

    public function build(string $orderTransactionId, Context $context, ?string $returnUrl = null): PaymentTransactionStruct
    {
        return new PaymentTransactionStruct($orderTransactionId, $returnUrl);
    }

    public function refund(string $refundId, string $orderTransactionId): RefundPaymentTransactionStruct
    {
        return new RefundPaymentTransactionStruct($refundId, $orderTransactionId);
    }
}
