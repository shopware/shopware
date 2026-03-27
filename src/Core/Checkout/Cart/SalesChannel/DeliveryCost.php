<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DeliveryCost extends Struct
{
    public function __construct(
        protected CalculatedPrice $shippingCost,
        protected DeliveryDate $deliveryDate,
        protected ShippingMethodEntity $shippingMethod,
    ) {
    }

    public function getShippingCost(): CalculatedPrice
    {
        return $this->shippingCost;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }
}
