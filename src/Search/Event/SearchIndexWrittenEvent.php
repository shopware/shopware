<?php declare(strict_types=1);

namespace Shopware\Search\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class SearchIndexWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_search_index.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_search_index';
    }
}
