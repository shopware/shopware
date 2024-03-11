<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\PaymentHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class SyncTestPaymentHandler implements SynchronousPaymentHandlerInterface
{
    public function __construct(private readonly OrderTransactionStateHandler $transactionStateHandler)
    {
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();
        $order = $transaction->getOrder();

        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            throw PaymentException::syncProcessInterrupted($transactionId, 'lineItems is null');
        }

        $customer = $order->getOrderCustomer()?->getCustomer();
        if ($customer === null) {
            throw PaymentException::syncProcessInterrupted($transactionId, 'customer is null');
        }

        $context = $salesChannelContext->getContext();
        $this->transactionStateHandler->process($transactionId, $context);
    }
}
