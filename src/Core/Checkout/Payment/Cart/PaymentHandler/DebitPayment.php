<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

// BC checker does not understand duplicate class declarations.
// Comment out the following code for tag:v6.7.0 and replace the existing class
/*
#[Package('checkout')]
class DebitPayment extends DefaultPayment
{
    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        $this->transactionStateHandler->process($transaction->getOrderTransactionId(), $context);

        return null;
    }
}
*/

#[Package('checkout')]
class DebitPayment extends DefaultPayment
{
    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());
    }
}
