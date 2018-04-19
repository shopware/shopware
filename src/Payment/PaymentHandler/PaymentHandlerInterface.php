<?php declare(strict_types=1);

namespace Shopware\Payment\PaymentHandler;

use Shopware\Context\Struct\ApplicationContext;
use Shopware\Payment\Struct\PaymentTransaction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

interface PaymentHandlerInterface
{
    /**
     * The pay function will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * @param PaymentTransaction $transaction
     * @param ApplicationContext $context
     *
     * @return null|RedirectResponse if a RedirectResponse is provided, a redirect to the url will be performed
     */
    public function pay(
        PaymentTransaction $transaction,
        ApplicationContext $context
    ): ?RedirectResponse;

    /**
     * The finalize function will be called when the user is redirected
     * back to shop from the payment gateway.
     *
     * @param string             $transactionId
     * @param Request            $request
     * @param ApplicationContext $context
     */
    public function finalize(
        string $transactionId,
        Request $request,
        ApplicationContext $context
    ): void;
}
