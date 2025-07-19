<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ProductListingResult>
 */
#[Package('inventory')]
class ProductSearchRouteResponse extends StoreApiResponse
{
    public function getListingResult(): ProductListingResult
    {
        return $this->object;
    }
}
