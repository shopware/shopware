<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\Content\Product\Collection\ProductTranslationBasicCollection;

class ProductTranslationSearchResult extends ProductTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
