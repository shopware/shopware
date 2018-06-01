<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Listing\Aggregate\ListingFacetTranslation\Event\ListingFacetTranslationBasicLoadedEvent;
use Shopware\System\Listing\Collection\ListingFacetDetailCollection;

class ListingFacetDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ListingFacetDetailCollection
     */
    protected $listingFacets;

    public function __construct(ListingFacetDetailCollection $listingFacets, Context $context)
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
