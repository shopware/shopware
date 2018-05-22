<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event;

use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class OrderWrittenEvent extends WrittenEvent
{
    public const NAME = 'order.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }
}
