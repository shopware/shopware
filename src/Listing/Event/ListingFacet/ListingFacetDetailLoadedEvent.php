<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingFacet;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Listing\Collection\ListingFacetDetailCollection;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationBasicLoadedEvent;

class ListingFacetDetailLoadedEvent extends NestedEvent
{
    const NAME = 'listing_facet.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingFacetDetailCollection
     */
    protected $listingFacets;

    public function __construct(ListingFacetDetailCollection $listingFacets, TranslationContext $context)
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

    public function getListingFacets(): ListingFacetDetailCollection
    {
        return $this->listingFacets;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->listingFacets->getTranslations()->count() > 0) {
            $events[] = new ListingFacetTranslationBasicLoadedEvent($this->listingFacets->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
