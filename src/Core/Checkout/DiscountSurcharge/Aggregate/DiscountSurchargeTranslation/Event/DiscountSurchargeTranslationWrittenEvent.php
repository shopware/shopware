<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class DiscountSurchargeTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'discount_surcharge_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return DiscountSurchargeTranslationDefinition::class;
    }
}
