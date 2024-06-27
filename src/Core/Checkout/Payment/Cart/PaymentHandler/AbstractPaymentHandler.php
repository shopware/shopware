<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
abstract class AbstractPaymentHandler
{
    /**
     * Will be checked, if any of the methods besides `validate`, `pay` or `finalize` can be called.
     * If the payment handler does not support the given `PaymentHandlerType`, it should return false.
     */
    abstract public function supports(
        PaymentHandlerType $type,
        string $paymentMethodId,
        Context $context
    ): bool;

    /**
     * Contains the main payment logic, that should prepare the payment and capture it, if it does not require a redirect.
     * Should return a `RedirectResponse` if the payment process requires a redirect, then `finalize` is going to be called with the `returnUrl` in the `PaymentTransactionStruct`.
     */
    abstract public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse;

    /**
     * Will be called, before the order is persisted.
     * If there is any processing, that was done before the order was created, it can be validated here.
     * If the validation fails, a `PaymentException` should be thrown. The order will not be persisted then.
     */
    public function validate(
        Cart $cart,
        RequestDataBag $dataBag,
        SalesChannelContext $context
    ): ?Struct {
        return null;
    }

    /**
     * This method will be called after the redirect, if the `pay` method returns a RedirectResponse.
     * If the `pay` method is not returning a RedirectResponse, this method will not and *cannot* be called.
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
    }

    /**
     * This method will only ever be called if the payment handler supports PaymentHandlerType::REFUND.
     * If the payment handler / method does not support refunds, a `PaymentException` should be thrown.
     *
     * The refund process of Shopware is only provided via API and has no frontend methods.
     */
    public function refund(
        RefundPaymentTransactionStruct $transaction,
        Context $context
    ): void {
        throw PaymentException::paymentHandlerTypeUnsupported($this, PaymentHandlerType::REFUND);
    }

    /**
     * This method will only ever be called if the payment handler supports PaymentHandlerType::RECURRING.
     * If the payment handler / method does not support recurring payments, a `PaymentException` should be thrown.
     *
     * Recurring payments are used capturing payments for subscriptions or other recurring payment methods without the user being present.
     * This is used e.g. by Shopware's commercial feature "Subscriptions", but is open to be supported by other implementations as well.
     */
    public function recurring(
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        throw PaymentException::paymentHandlerTypeUnsupported($this, PaymentHandlerType::RECURRING);
    }
}
