<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event;

use Shopware\Checkout\Customer\CustomerDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CustomerDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'customer.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerDefinition::class;
    }
}
