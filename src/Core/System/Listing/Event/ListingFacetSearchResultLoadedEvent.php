<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Struct\ListingFacetSearchResult;

class ListingFacetSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet.search.result.loaded';

    /**
     * @var ListingFacetSearchResult
     */
    protected $result;

    public function __construct(ListingFacetSearchResult $result)
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
