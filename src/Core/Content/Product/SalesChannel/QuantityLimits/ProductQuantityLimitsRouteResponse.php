<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ProductQuantityLimitsResult>
 */
#[Package('inventory')]
class ProductQuantityLimitsRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductQuantityLimitsResult
    {
        return $this->object;
    }
}
