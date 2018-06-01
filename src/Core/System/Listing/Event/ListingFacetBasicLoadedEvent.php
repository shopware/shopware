<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Collection\ListingFacetBasicCollection;

class ListingFacetBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ListingFacetBasicCollection
     */
    protected $listingFacets;

    public function __construct(ListingFacetBasicCollection $listingFacets, Context $context)
    {
        $this->context = $context;
        $this->listingFacets = $listingFacets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getListingFacets(): ListingFacetBasicCollection
    {
        return $this->listingFacets;
    }
}
