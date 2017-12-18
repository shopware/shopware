<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethodPrice;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shipping\Definition\ShippingMethodPriceDefinition;

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
