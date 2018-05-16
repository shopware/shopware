<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event;

use Shopware\Checkout\Customer\CustomerDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CustomerWrittenEvent extends WrittenEvent
{
    public const NAME = 'customer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerDefinition::class;
    }
}
