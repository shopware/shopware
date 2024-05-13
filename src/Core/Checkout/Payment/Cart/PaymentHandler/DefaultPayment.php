<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

// BC checker does not understand duplicate class declarations.
// Comment out the following code for tag:v6.7.0 and replace the existing class
/*
#[Package('checkout')]
class DefaultPayment extends AbstractPaymentHandler
{
    /**
     * @internal
     *
    public function __construct(protected readonly OrderTransactionStateHandler $transactionStateHandler)
    {
        $this->transactionStateHandler = $transactionStateHandler;
    }

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
*/

// @phpstan-ignore-next-line
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
