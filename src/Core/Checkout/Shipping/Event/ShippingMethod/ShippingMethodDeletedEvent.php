<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethod;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Shipping\Definition\ShippingMethodDefinition;

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
