<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use Shopware\Core\Content\Sitemap\Struct\SitemapCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SitemapCollection>
 */
#[Package('discovery')]
class SitemapRouteResponse extends StoreApiResponse
{
    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}
