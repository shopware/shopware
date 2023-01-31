<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductDetailRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
