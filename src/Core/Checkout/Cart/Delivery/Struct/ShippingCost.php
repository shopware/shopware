<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class ShippingCost extends Struct
{
    public function __construct(
        public readonly CalculatedPrice $shippingCost,
        public readonly DeliveryDate $deliveryDate,
        public readonly ShippingMethodEntity $shippingMethod,
    ) {
    }
}
