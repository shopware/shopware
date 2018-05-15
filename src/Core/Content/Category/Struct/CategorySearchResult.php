<?php declare(strict_types=1);

namespace Shopware\Content\Category\Struct;

use Shopware\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
