<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Collection\ProductTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ProductTranslationSearchResult extends ProductTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
