<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

interface AsynchronousPaymentHandlerInterface
{
    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * A redirect to the url will be performed
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, Context $context): RedirectResponse;

    /**
     * The finalize function will be called when the user is redirected back to shop from the payment gateway.
     */
    public function finalize(string $transactionId, Request $request, Context $context): void;
}
