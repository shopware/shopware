<?php declare(strict_types=1);

namespace Shopware\Search\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class SearchFieldsWrittenEvent extends EntityWrittenEvent
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
