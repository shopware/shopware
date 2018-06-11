<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
