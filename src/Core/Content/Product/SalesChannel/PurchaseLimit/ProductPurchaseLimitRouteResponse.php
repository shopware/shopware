<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ProductPurchaseLimitCollection>
 */
#[Package('inventory')]
class ProductPurchaseLimitRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductPurchaseLimitCollection
    {
        return $this->object;
    }
}
