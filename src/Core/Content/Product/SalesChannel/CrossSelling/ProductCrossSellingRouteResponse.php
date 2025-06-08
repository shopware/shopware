<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CrossSellingElementCollection>
 */
#[Package('inventory')]
class ProductCrossSellingRouteResponse extends StoreApiResponse
{
    public function getResult(): CrossSellingElementCollection
    {
        return $this->object;
    }
}
