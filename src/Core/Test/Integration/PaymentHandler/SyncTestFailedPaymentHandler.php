<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - will be removed with new payment handlers
 */
#[Package('checkout')]
class SyncTestFailedPaymentHandler implements SynchronousPaymentHandlerInterface
{
    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        throw PaymentException::syncProcessInterrupted($transaction->getOrderTransaction()->getId(), 'This is a TestPaymentHandler which will always fail');
    }
}
