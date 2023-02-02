<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DefaultPayment implements SynchronousPaymentHandlerInterface
{
    /**
     * @var OrderTransactionStateHandler
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
