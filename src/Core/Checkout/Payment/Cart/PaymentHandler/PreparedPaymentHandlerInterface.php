<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CapturePreparedPaymentException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal (flag:FEATURE_NEXT_16769)
 */
interface PreparedPaymentHandlerInterface extends PaymentHandlerInterface
{
    /**
     * The validate method will be called before actually capturing the payment.
     * It allows the validation of the supplied payment transaction.
     *
     * @throws ValidatePreparedPaymentException
     */
    public function validate(
        PreparedPaymentTransactionStruct $preparedPaymentTransactionStruct,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context
    ): Struct;

    /**
     * The capture method will be called, after successfully validating the payment before
     *
     * @throws CapturePreparedPaymentException
     */
    public function capture(
        PreparedPaymentTransactionStruct $preparedPaymentTransactionStruct,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        Struct $preOrderPaymentStruct
    ): void;
}
