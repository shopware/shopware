<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacetTranslation;

use Shopware\System\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingFacetTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ListingFacetTranslationBasicCollection
     */
    protected $listingFacetTranslations;

    public function __construct(ListingFacetTranslationBasicCollection $listingFacetTranslations, ApplicationContext $context)
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

    public function getListingFacetTranslations(): ListingFacetTranslationBasicCollection
    {
        return $this->listingFacetTranslations;
    }
}
