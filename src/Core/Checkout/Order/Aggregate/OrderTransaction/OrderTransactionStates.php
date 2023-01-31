<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
final class OrderTransactionStates
{
    public const STATE_MACHINE = 'order_transaction.state';
    public const STATE_OPEN = 'open';
    public const STATE_PAID = 'paid';
    public const STATE_PARTIALLY_PAID = 'paid_partially';
    public const STATE_REFUNDED = 'refunded';
    public const STATE_PARTIALLY_REFUNDED = 'refunded_partially';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_REMINDED = 'reminded';
    public const STATE_AUTHORIZED = 'authorized';
    public const STATE_FAILED = 'failed';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_CHARGEBACK = 'chargeback';
    public const STATE_UNCONFIRMED = 'unconfirmed';
}
