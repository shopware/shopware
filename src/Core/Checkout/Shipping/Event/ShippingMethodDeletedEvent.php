<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event;

use Shopware\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ShippingMethodDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shipping_method.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodDefinition::class;
    }
}
