<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Struct;

use Shopware\Core\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
