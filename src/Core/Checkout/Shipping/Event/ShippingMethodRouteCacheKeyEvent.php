<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
