<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class OrderDeliveryStates
{
    public const string STATE_MACHINE = 'order_delivery.state';
    public const string STATE_OPEN = 'open';
    public const string STATE_PARTIALLY_SHIPPED = 'shipped_partially';
    public const string STATE_SHIPPED = 'shipped';
    public const string STATE_PARTIALLY_DELIVERED = 'delivered_partially';
    public const string STATE_DELIVERED = 'delivered';
    public const string STATE_PARTIALLY_RETURNED = 'returned_partially';
    public const string STATE_RETURNED = 'returned';
    public const string STATE_CANCELLED = 'cancelled';
}
