<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Event;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
