<?php declare(strict_types=1);

namespace Shopware\Category\Searcher;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
