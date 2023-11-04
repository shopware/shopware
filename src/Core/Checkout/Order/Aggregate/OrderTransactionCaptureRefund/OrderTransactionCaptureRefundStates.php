<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
final class OrderTransactionCaptureRefundStates
{
    public const STATE_MACHINE = 'order_transaction_capture_refund.state';
    public const STATE_OPEN = 'open';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_FAILED = 'failed';
    public const STATE_COMPLETED = 'completed';
}
