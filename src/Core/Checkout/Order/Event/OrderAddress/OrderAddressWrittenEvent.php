<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderAddress;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderAddressDefinition;

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
