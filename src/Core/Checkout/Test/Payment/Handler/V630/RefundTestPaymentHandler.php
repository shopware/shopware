<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Framework\Context;

/**
 * @package checkout
 *
 * @internal
 */
class RefundTestPaymentHandler implements RefundPaymentHandlerInterface
{
    private OrderTransactionCaptureRefundStateHandler $stateHandler;

    public function __construct(OrderTransactionCaptureRefundStateHandler $stateHandler)
    {
        $this->stateHandler = $stateHandler;
    }

    public function refund(string $refundId, Context $context): void
    {
        $this->stateHandler->complete($refundId, $context);
    }
}
