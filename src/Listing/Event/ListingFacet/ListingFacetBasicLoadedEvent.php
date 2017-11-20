<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingFacet;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Listing\Collection\ListingFacetBasicCollection;

class ListingFacetBasicLoadedEvent extends NestedEvent
{
    const NAME = 'listing_facet.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingFacetBasicCollection
     */
    protected $listingFacets;

    public function __construct(ListingFacetBasicCollection $listingFacets, TranslationContext $context)
    {
        $this->context = $context;
        $this->listingFacets = $listingFacets;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getListingFacets(): ListingFacetBasicCollection
    {
        return $this->listingFacets;
    }
}
