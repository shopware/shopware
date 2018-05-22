<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductTranslation\Struct;

use Shopware\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ProductTranslationSearchResult extends ProductTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
