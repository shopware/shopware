<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class ShippingMethodTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'shipping_method_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodTranslationDefinition::class;
    }
}
