<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Searcher;

use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
