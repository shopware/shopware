<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacetTranslation;

use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\System\Listing\Collection\ListingFacetTranslationDetailCollection;
use Shopware\System\Listing\Event\ListingFacet\ListingFacetBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ListingFacetTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ListingFacetTranslationDetailCollection
     */
    protected $listingFacetTranslations;

    public function __construct(ListingFacetTranslationDetailCollection $listingFacetTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->listingFacetTranslations = $listingFacetTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
