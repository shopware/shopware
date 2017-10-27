<?php declare(strict_types=1);

namespace Shopware\Api\Search\Event;

use Shopware\Api\Write\WrittenEvent;

class SearchKeywordsWrittenEvent extends WrittenEvent
{
    const NAME = 's_search_keywords.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_search_keywords';
    }
}
