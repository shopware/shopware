<?php

namespace Shopware\Shop\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Struct\ShopSearchResult;
use Shopware\Search\Criteria;
use Symfony\Component\EventDispatcher\Event;

class ShopSearchResultEvent extends Event
{
    const NAME = 'shops.search.result.loaded';

    /**
     * @var ShopSearchResult
     */
    private $searchResult;

    /**
     * @var TranslationContext
     */
    private $context;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(ShopSearchResult $searchResult, Criteria $criteria, TranslationContext $context)
    {
        $this->searchResult = $searchResult;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getSearchResult(): ShopSearchResult
    {
        return $this->searchResult;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}