<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacet;

use Shopware\System\Listing\Collection\ListingFacetBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingFacetBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ListingFacetBasicCollection
     */
    protected $listingFacets;

    public function __construct(ListingFacetBasicCollection $listingFacets, ApplicationContext $context)
    {
        $this->context = $context;
        $this->listingFacets = $listingFacets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getListingFacets(): ListingFacetBasicCollection
    {
        return $this->listingFacets;
    }
}
