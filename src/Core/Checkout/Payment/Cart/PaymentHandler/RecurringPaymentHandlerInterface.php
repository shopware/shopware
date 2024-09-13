<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - will be removed, extend AbstractPaymentHandler instead
 */
#[Package('checkout')]
interface RecurringPaymentHandlerInterface extends PaymentHandlerInterface
{
    /**
     * The captureRecurring function is called for every recurring payment of a subscription.
     * A successful billing agreement with the payment provider should exist at this moment.
     * Initial billing agreements should be handled via the other payment methods
     * (@see SynchronousPaymentHandlerInterface, AsynchronousPaymentHandlerInterface for instance).
     * The handler should only be called in the background by scheduled tasks, etc.
     *
     * Throw a @see PaymentException::recurringInterrupted() exception if an error ocurres while processing the payment
     *
     * @throws PaymentException
     */
    public function captureRecurring(RecurringPaymentTransactionStruct $transaction, Context $context): void;
}
