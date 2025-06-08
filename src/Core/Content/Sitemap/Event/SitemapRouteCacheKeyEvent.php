<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class SitemapRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
