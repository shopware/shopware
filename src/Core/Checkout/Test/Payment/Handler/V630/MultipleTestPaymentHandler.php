<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class MultipleTestPaymentHandler implements SynchronousPaymentHandlerInterface, PreparedPaymentHandlerInterface
{
    public function validate(
        Cart $cart,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context
    ): Struct {
        return new ArrayStruct();
    }

    public function capture(
        PreparedPaymentTransactionStruct $transaction,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        Struct $preOrderPaymentStruct
    ): void {
    }

    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
    }
}
