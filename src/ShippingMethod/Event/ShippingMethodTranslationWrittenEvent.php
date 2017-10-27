<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\Api\Write\WrittenEvent;

class ShippingMethodTranslationWrittenEvent extends WrittenEvent
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
