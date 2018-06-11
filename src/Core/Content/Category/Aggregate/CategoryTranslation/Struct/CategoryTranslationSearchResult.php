<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Struct;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Collection\CategoryTranslationBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class CategoryTranslationSearchResult extends CategoryTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
