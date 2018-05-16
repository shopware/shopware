<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerTranslationSearchResult extends ProductManufacturerTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
