<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ShippingMethodTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shipping_method_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShippingMethodTranslationDefinition::class;
    }
}
