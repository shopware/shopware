<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Category\Collection\CategoryBasicCollection;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
