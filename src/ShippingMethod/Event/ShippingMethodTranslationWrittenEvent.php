<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ShippingMethodTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'shipping_method_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shipping_method_translation';
    }
}
