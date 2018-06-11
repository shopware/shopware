<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Listing\Struct\ListingFacetSearchResult;

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
