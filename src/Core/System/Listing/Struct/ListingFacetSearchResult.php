<?php declare(strict_types=1);

namespace Shopware\System\Listing\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Listing\Collection\ListingFacetBasicCollection;

class ListingFacetSearchResult extends ListingFacetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
