<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingMethodRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
