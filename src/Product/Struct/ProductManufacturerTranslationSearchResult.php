<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Product\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerTranslationSearchResult extends ProductManufacturerTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
