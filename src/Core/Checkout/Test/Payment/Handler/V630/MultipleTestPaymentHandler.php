<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class MultipleTestPaymentHandler implements SynchronousPaymentHandlerInterface, PreparedPaymentHandlerInterface
{
    public function validate(Request $request, Cart $cart): Struct
    {
        return new ArrayStruct();
    }

    public function capture(Request $request, OrderEntity $order, Struct $preOrderPaymentStruct): void
    {
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
    }
}
