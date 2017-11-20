<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethodPrice;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shipping\Definition\ShippingMethodPriceDefinition;

class ShippingMethodPriceWrittenEvent extends WrittenEvent
{
    const NAME = 'shipping_method_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceDefinition::class;
    }
}
