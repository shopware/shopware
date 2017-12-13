<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderAddress;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Order\Definition\OrderAddressDefinition;

class OrderAddressWrittenEvent extends WrittenEvent
{
    const NAME = 'order_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderAddressDefinition::class;
    }
}
