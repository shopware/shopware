<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Customer\Definition\CustomerGroupTranslationDefinition;

class CustomerGroupTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupTranslationDefinition::class;
    }
}
