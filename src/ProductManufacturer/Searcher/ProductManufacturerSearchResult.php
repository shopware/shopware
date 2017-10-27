<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
