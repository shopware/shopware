<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
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
    class DefaultPayment extends AbstractPaymentHandler
    {
        public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
        {
            // needed for payment methods like Cash on delivery and Paid in advance
            return null;
        }

        public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
        {
            return false;
        }
    }
} else {
    /**
     * @deprecated tag:v6.7.0 - reason:becomes-internal
     */
    #[Package('checkout')]
    class DefaultPayment implements SynchronousPaymentHandlerInterface
    {
        /**
         * @var OrderTransactionStateHandler
         *
         * @deprecated tag:v6.7.0 - will be removed for DefaultPayments
         */
        protected $transactionStateHandler;

        /**
         * @internal
         */
        public function __construct(OrderTransactionStateHandler $transactionStateHandler)
        {
            $this->transactionStateHandler = $transactionStateHandler;
        }

        public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
        {
            // needed for payment methods like Cash on delivery and Paid in advance
        }
    }
}
