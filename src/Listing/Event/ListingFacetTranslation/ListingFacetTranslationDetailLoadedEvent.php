<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingFacetTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\Listing\Event\ListingFacet\ListingFacetBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class ListingFacetTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'listing_facet_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingFacetTranslationDetailCollection
     */
    protected $listingFacetTranslations;

    public function __construct(ListingFacetTranslationDetailCollection $listingFacetTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->listingFacetTranslations = $listingFacetTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->listingFacetTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
