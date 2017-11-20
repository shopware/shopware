<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSorting;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Listing\Struct\ListingSortingSearchResult;

class ListingSortingSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'listing_sorting.search.result.loaded';

    /**
     * @var ListingSortingSearchResult
     */
    protected $result;

    public function __construct(ListingSortingSearchResult $result)
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
