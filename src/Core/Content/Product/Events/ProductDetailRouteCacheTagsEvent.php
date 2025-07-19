<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class ProductDetailRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
