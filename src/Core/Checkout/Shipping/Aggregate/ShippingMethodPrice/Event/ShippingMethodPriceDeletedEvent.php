<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ShippingMethodPriceDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shipping_method_price.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodPriceDefinition::class;
    }
}
