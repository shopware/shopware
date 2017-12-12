<?php declare(strict_types=1);

namespace Shopware\Search\Event\SearchKeyword;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Search\Collection\SearchKeywordBasicCollection;

class SearchKeywordBasicLoadedEvent extends NestedEvent
{
    const NAME = 'search_keyword.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var SearchKeywordBasicCollection
     */
    protected $searchKeywords;

    public function __construct(SearchKeywordBasicCollection $searchKeywords, TranslationContext $context)
    {
        $this->context = $context;
        $this->searchKeywords = $searchKeywords;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getSearchKeywords(): SearchKeywordBasicCollection
    {
        return $this->searchKeywords;
    }
}
