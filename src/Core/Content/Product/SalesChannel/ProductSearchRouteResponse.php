<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\System\SalesChannel\SalesChannelApiResponse;

class ProductSearchRouteResponse extends SalesChannelApiResponse
{
    /**
     * @var ProductListingResult
     */
    protected $object;

    public function getListingResult(): ProductListingResult
    {
        return $this->object;
    }
}
