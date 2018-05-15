<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerTranslationSearchResult extends ProductManufacturerTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
