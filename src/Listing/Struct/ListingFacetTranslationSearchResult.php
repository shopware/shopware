<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetTranslationSearchResult extends ListingFacetTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
