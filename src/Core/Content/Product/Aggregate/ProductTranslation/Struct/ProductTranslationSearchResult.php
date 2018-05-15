<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationBasicCollection;

class ProductTranslationSearchResult extends ProductTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
