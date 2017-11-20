<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Listing\Collection\ListingFacetBasicCollection;

class ListingFacetSearchResult extends ListingFacetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
