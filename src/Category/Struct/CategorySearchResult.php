<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Category\Collection\CategoryBasicCollection;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
