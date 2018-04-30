<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Listing\Collection\ListingFacetBasicCollection;

class ListingFacetSearchResult extends ListingFacetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
