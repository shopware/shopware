<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class ProductSearchRouteResponse extends StoreApiResponse
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
