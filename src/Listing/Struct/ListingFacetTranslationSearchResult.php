<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetTranslationSearchResult extends ListingFacetTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
