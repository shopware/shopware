<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ShippingMethodCollection>>
 */
#[Package('checkout')]
class ShippingMethodRouteResponse extends StoreApiResponse
{
    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->object->getEntities();
    }
}
