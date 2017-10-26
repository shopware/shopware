<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ShippingMethodWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'shipping_method.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shipping_method';
    }
}
