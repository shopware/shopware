<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SeoUrlRouteResponse extends StoreApiResponse
{
    /**
     * @var SeoUrlCollection
     */
    protected $object;

    public function __construct(SeoUrlCollection $object)
    {
        parent::__construct($object);
    }

    public function getSeoUrls(): SeoUrlCollection
    {
        return $this->object;
    }
}
