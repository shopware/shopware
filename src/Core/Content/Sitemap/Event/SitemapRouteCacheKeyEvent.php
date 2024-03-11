<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class SitemapRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
