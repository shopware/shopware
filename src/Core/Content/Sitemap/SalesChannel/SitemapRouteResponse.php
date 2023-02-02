<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use Shopware\Core\Content\Sitemap\Struct\SitemapCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SitemapRouteResponse extends StoreApiResponse
{
    /**
     * @var SitemapCollection
     */
    protected $object;

    public function __construct(SitemapCollection $object)
    {
        parent::__construct($object);
    }

    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}
