<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
