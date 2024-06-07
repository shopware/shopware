<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

if (Feature::isActive('v6.7.0.0')) {
    /**
     * @internal
     */
    #[Package('checkout')]
    class DebitPayment extends DefaultPayment
    {
        public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
        {
            $this->transactionStateHandler->process($transaction->getOrderTransactionId(), $context);

            return null;
        }
    }
} else {
    /**
     * @deprecated tag:v6.7.0 - reason:becomes-internal
     */
    #[Package('checkout')]
    class DebitPayment extends DefaultPayment
    {
        public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
        {
            $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());
        }
    }
}
