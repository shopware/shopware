<?php declare(strict_types=1);

namespace Shopware\Content\Category\Struct;

use Shopware\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
