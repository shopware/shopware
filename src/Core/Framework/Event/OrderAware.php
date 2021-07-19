<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * @internal (FEATURE_NEXT_8225)
 */
interface OrderAware extends ShopwareEvent
{
    public function getOrderId(): string;
}
