<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

interface PaymentHandlerInterface
{
    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * @param \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction
     * @param \Shopware\Core\Framework\Context                                       $context
     *
     * @return null|RedirectResponse if a RedirectResponse is provided, a redirect to the url will be performed
     */
    public function pay(
        PaymentTransactionStruct $transaction,
        Context $context
    ): ?RedirectResponse;

    /**
     * The finalize function will be called when the user is redirected
     * back to shop from the payment gateway.
     *
     * @param string             $transactionId
     * @param Request            $request
     * @param \Shopware\Core\Framework\Context $context
     */
    public function finalize(
        string $transactionId,
        Request $request,
        Context $context
    ): void;
}
