<?php declare(strict_types=1);

namespace Shopware\Payment\PaymentHandler;

use Shopware\Context\Struct\ShopContext;
use Shopware\Payment\Struct\PaymentTransaction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

interface PaymentHandlerInterface
{
    /**
     * The payAction will be called after the customer completed the order.
     * Allows to process the order and store additional information.
     *
     * @return null|RedirectResponse if a RedirectResponse is provided, a redirect to the url will be performed
     */
    public function payAction(
        PaymentTransaction $paymentTransaction,
        ShopContext $context
    ): ?RedirectResponse;

    /**
     * The finalizePaymentAction will be called when the user is redirected
     * back to shop from the payment gateway.
     */
    public function finalizePaymentAction(
        string $transactionId,
        Request $request,
        ShopContext $context
    ): void;
}
