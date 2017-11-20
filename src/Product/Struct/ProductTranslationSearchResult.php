<?php declare(strict_types=1);

namespace Shopware\Product\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Product\Collection\ProductTranslationBasicCollection;

class ProductTranslationSearchResult extends ProductTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
