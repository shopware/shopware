<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

// BC checker does not understand duplicate class declarations.
// Comment out the following code for tag:v6.7.0 and replace the existing class
/*
#[Package('checkout')]
class InvoicePayment extends DefaultPayment
{
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type === PaymentHandlerType::RECURRING;
    }
}
*/

// @phpstan-ignore-next-line
#[Package('checkout')]
class InvoicePayment extends DefaultPayment implements RecurringPaymentHandlerInterface
{
    public function captureRecurring(RecurringPaymentTransactionStruct $transaction, Context $context): void
    {
    }
}
