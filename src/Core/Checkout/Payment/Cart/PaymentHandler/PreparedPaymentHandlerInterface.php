<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface PreparedPaymentHandlerInterface extends PaymentHandlerInterface
{
    /**
     * The validate method will be called before actually placing the order.
     * It allows the validation of the supplied payment transaction.
     *
     * @throws PaymentException
     */
    public function validate(
        Cart $cart,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context
    ): Struct;

    /**
     * The capture method will be called, after successfully validating the payment before
     *
     * @throws PaymentException - PaymentException::PAYMENT_CAPTURE_PREPARED_ERROR
     */
    public function capture(
        PreparedPaymentTransactionStruct $transaction,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        Struct $preOrderPaymentStruct
    ): void;
}
