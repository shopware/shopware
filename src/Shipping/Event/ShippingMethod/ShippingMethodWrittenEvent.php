<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethod;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shipping\Definition\ShippingMethodDefinition;

class ShippingMethodWrittenEvent extends WrittenEvent
{
    const NAME = 'shipping_method.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodDefinition::class;
    }
}
