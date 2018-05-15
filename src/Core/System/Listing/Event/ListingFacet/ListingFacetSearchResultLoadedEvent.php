<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacet;

use Shopware\System\Listing\Struct\ListingFacetSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
