<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - will be removed, extend AbstractPaymentHandler instead
 */
#[Package('checkout')]
interface AsynchronousPaymentHandlerInterface extends PaymentHandlerInterface
{
    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * A redirect to the url will be performed
     *
     * Throw a @see PaymentException::PAYMENT_ASYNC_PROCESS_INTERRUPTED exception if an error ocurres while processing the payment
     *
     * @throws PaymentException
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse;

    /**
     * The finalize function will be called when the user is redirected back to shop from the payment gateway.
     *
     * Throw a @see PaymentException::PAYMENT_ASYNC_FINALIZE_INTERRUPTED exception if an error ocurres while calling an external payment API
     * Throw a @see PaymentException::PAYMENT_CUSTOMER_CANCELED_EXTERNAL exception if the customer canceled the payment process on
     * payment provider page
     *
     * @throws PaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void;
}
