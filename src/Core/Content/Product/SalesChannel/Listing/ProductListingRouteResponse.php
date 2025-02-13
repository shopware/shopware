<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ProductListingResult>
 */
#[Package('inventory')]
class ProductListingRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductListingResult
    {
        return $this->object;
    }
}
