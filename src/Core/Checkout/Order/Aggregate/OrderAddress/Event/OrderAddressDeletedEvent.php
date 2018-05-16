<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderAddress\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;

class OrderAddressDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_address.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderAddressDefinition::class;
    }
}
