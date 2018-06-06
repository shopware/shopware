<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Event;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class DiscountSurchargeWrittenEvent extends WrittenEvent
{
    public const NAME = 'discount_surcharge.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return DiscountSurchargeDefinition::class;
    }
}
