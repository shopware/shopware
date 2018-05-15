<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
