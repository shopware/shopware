<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Collection\ProductManufacturerBasicCollection;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
