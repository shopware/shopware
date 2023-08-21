<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class InvoicePayment extends DefaultPayment implements RecurringPaymentHandlerInterface
{
    public function captureRecurring(RecurringPaymentTransactionStruct $transaction, Context $context): void
    {
    }
}
