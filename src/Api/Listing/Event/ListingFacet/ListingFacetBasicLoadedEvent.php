<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingFacet;

use Shopware\Api\Listing\Collection\ListingFacetBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

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
