<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ShippingMethodPriceWrittenEvent extends WrittenEvent
{
    public const NAME = 'shipping_method_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceDefinition::class;
    }
}
