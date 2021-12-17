<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PreparedTestPaymentHandler implements PreparedPaymentHandlerInterface
{
    public function validate(
        PreparedPaymentTransactionStruct $preparedPaymentTransactionStruct,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context
    ): Struct {
        return new ArrayStruct();
    }

    public function capture(
        PreparedPaymentTransactionStruct $preparedPaymentTransactionStruct,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        Struct $preOrderPaymentStruct
    ): void {
    }
}
