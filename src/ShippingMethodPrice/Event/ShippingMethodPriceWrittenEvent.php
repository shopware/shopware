<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Event;

use Shopware\Api\Write\WrittenEvent;

class ShippingMethodPriceWrittenEvent extends WrittenEvent
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
