<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCostCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<DeliveryCostCollection>
 */
#[Package('checkout')]
class DeliveryCostRouteResponse extends StoreApiResponse
{
    public function getDeliveryCosts(): DeliveryCostCollection
    {
        return $this->object;
    }

    public function getShippingCost(string $shippingMethodId): ?CalculatedPrice
    {
        return $this->object->get($shippingMethodId)?->shippingCost;
    }

    public function getDeliveryDate(string $shippingMethodId): ?DeliveryDate
    {
        return $this->object->get($shippingMethodId)?->deliveryDate;
    }

    public function getShippingMethod(string $shippingMethodId): ?ShippingMethodEntity
    {
        return $this->object->get($shippingMethodId)?->shippingMethod;
    }
}
