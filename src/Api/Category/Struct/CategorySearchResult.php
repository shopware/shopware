<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CategorySearchResult extends CategoryBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
