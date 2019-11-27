<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SitemapListerInterface
{
    /**
     * @return Sitemap[]
     */
    public function getSitemaps(SalesChannelContext $salesChannelContext): array;
}
