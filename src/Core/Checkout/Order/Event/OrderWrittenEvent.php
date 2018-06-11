<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
