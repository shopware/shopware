<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingFacetTranslation;

use Shopware\System\Listing\Struct\ListingFacetTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingFacetTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_facet_translation.search.result.loaded';

    /**
     * @var ListingFacetTranslationSearchResult
     */
    protected $result;

    public function __construct(ListingFacetTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
