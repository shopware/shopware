<?php declare(strict_types=1);

namespace Shopware\Search\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class SearchFieldsWrittenEvent extends AbstractWrittenEvent
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
