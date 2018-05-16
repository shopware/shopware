<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingFacetTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetTranslationSearchResult extends ListingFacetTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
