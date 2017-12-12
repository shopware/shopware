<?php declare(strict_types=1);

namespace Shopware\Search\Event\SearchKeyword;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Search\Struct\SearchKeywordSearchResult;

class SearchKeywordSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'search_keyword.search.result.loaded';

    /**
     * @var SearchKeywordSearchResult
     */
    protected $result;

    public function __construct(SearchKeywordSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
