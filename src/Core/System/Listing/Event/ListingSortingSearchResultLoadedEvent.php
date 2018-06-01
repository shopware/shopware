<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Struct\ListingSortingSearchResult;

class ListingSortingSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.search.result.loaded';

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
