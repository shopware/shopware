<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Event;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class DiscountSurchargeDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'discount_surcharge.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return DiscountSurchargeDefinition::class;
    }
}
