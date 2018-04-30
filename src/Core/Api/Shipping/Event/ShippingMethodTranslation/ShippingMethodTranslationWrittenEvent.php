<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethodTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shipping\Definition\ShippingMethodTranslationDefinition;

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
