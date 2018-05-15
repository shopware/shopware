<?php declare(strict_types=1);

namespace Shopware\Content\Category\Struct;

use Shopware\Content\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class CategoryTranslationSearchResult extends CategoryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
