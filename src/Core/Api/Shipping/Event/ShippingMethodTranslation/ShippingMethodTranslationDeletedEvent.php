<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethodTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shipping\Definition\ShippingMethodTranslationDefinition;

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
