<?php declare(strict_types=1);

namespace Shopware\Search\Event\SearchKeyword;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Search\Definition\SearchKeywordDefinition;

class SearchKeywordWrittenEvent extends WrittenEvent
{
    const NAME = 'search_keyword.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return SearchKeywordDefinition::class;
    }
}
