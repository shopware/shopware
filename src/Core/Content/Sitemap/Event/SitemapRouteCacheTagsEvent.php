<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;

/**
 * @package sales-channel
 */
#[Package('sales-channel')]
class SitemapRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
