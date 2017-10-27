<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Api\Write\WrittenEvent;

class CustomerGroupTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'customer_group_translation';
    }
}
