<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class SearchFieldsWrittenEvent extends WrittenEvent
{
    const NAME = 's_search_fields.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_search_fields';
    }
}
