<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;

class ProductListingPriceSearchResult extends ProductListingPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
