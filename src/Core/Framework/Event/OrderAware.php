<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
#[IsFlowEventAware]
interface OrderAware
{
    //todo@skroblin remove order object from flow data
    public const ORDER = 'order';

    public const ORDER_ID = 'orderId';

    public function getOrderId(): string;
}
