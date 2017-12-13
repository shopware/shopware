<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Category\Collection\CategoryTranslationBasicCollection;

class CategoryTranslationSearchResult extends CategoryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
