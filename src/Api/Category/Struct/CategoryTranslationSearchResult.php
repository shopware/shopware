<?php declare(strict_types=1);

namespace Shopware\Api\Category\Struct;

use Shopware\Api\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CategoryTranslationSearchResult extends CategoryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
