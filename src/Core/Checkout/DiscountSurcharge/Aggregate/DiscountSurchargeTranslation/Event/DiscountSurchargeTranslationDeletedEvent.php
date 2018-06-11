<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class DiscountSurchargeTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'discount_surcharge_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return DiscountSurchargeTranslationDefinition::class;
    }
}
