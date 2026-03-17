<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ProductQuantityLimitsResultCollection>
 */
#[Package('inventory')]
class ProductQuantityLimitsRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductQuantityLimitsResultCollection
    {
        return $this->object;
    }
}
