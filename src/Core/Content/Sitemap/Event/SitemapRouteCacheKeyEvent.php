<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;

/**
 * @package sales-channel
 */
#[Package('sales-channel')]
class SitemapRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
