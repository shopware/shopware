<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * @deprecated tag:v6.4.0 - deprecated since 6.3.0 will be removed in 6.4.0
 */
class ShippingMethodPriceDeprecationUpdater
{
    public function updateByEvent(EntityWrittenEvent $event): void
    {
        //deprecated
    }

    public function updateByShippingMethodId(array $shippingMethodIds): void
    {
        //deprecated
    }
}
