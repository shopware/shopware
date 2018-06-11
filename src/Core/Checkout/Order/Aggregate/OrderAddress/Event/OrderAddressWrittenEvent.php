<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class OrderAddressWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderAddressDefinition::class;
    }
}
