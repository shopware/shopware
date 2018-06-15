<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Core\System\Listing\Event\ListingFacetBasicLoadedEvent;

class ListingFacetTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationDetailCollection
     */
    protected $listingFacetTranslations;

    public function __construct(ListingFacetTranslationDetailCollection $listingFacetTranslations, Context $context)
    {
        $this->context = $context;
        $this->listingFacetTranslations = $listingFacetTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getListingFacetTranslations(): ListingFacetTranslationDetailCollection
    {
        return $this->listingFacetTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->listingFacetTranslations->getListingFacets()->count() > 0) {
            $events[] = new ListingFacetBasicLoadedEvent($this->listingFacetTranslations->getListingFacets(), $this->context);
        }
        if ($this->listingFacetTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->listingFacetTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
