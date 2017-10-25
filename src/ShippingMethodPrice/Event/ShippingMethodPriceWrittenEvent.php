<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ShippingMethodPriceWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'shipping_method_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shipping_method_price';
    }
}
