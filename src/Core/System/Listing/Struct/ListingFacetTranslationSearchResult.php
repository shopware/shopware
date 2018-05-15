<?php declare(strict_types=1);

namespace Shopware\System\Listing\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\System\Listing\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetTranslationSearchResult extends ListingFacetTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
