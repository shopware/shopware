<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingFacetTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'listing_facet_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ListingFacetTranslationBasicCollection
     */
    protected $listingFacetTranslations;

    public function __construct(ListingFacetTranslationBasicCollection $listingFacetTranslations, TranslationContext $context)
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

    public function getListingFacetTranslations(): ListingFacetTranslationBasicCollection
    {
        return $this->listingFacetTranslations;
    }
}
