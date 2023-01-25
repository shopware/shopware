<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class RefundTestPaymentHandler implements RefundPaymentHandlerInterface
{
    public function __construct(private readonly OrderTransactionCaptureRefundStateHandler $stateHandler)
    {
    }

    public function refund(string $refundId, Context $context): void
    {
        $this->stateHandler->complete($refundId, $context);
    }
}
