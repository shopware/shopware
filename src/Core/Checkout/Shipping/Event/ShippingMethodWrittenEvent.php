<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Shipping\ShippingMethodDefinition;

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
