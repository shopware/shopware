<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * This factory is intended to be decorated in order to manipulate the structs that are used in the payment process by the payment handlers
 */
#[Package('checkout')]
abstract class AbstractPaymentTransactionStructFactory
{
    abstract public function getDecorated(): AbstractPaymentTransactionStructFactory;

    abstract public function build(string $orderTransactionId, Context $context, ?string $returnUrl = null): PaymentTransactionStruct;

    abstract public function refund(string $refundId, string $orderTransactionId): RefundPaymentTransactionStruct;
}
