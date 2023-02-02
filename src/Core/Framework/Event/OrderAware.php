<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface OrderAware extends FlowEventAware
{
    public const ORDER = 'order';

    public const ORDER_ID = 'orderId';

    public function getOrderId(): string;
}
