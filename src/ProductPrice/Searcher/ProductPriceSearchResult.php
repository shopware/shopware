<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;

class ProductPriceSearchResult extends ProductPriceBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
