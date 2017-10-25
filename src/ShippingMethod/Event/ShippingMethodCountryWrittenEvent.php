<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ShippingMethodCountryWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'shipping_method_country.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shipping_method_country';
    }
}
