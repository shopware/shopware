<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class PrePayment extends DefaultPayment
{
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type === PaymentHandlerType::RECURRING;
    }

    public function recurring(PaymentTransactionStruct $transaction, Context $context): void
    {
    }
}
