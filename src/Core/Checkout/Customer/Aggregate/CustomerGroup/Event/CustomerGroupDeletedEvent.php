<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroup\Event;

use Shopware\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CustomerGroupDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'customer_group.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDefinition::class;
    }
}
