<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
final class OrderTransactionCaptureStates
{
    public const STATE_MACHINE = 'order_transaction_capture.state';
    public const STATE_PENDING = 'pending';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';
}
