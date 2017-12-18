<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupTranslation;

use Shopware\Api\Customer\Definition\CustomerGroupTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerGroupTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'customer_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupTranslationDefinition::class;
    }
}
