<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethod;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;

class ShippingMethodWrittenEvent extends WrittenEvent
{
    public const NAME = 'shipping_method.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodDefinition::class;
    }
}
