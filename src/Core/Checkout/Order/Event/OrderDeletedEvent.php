<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\OrderDefinition;

class OrderDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }
}
