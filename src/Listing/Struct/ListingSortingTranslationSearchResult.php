<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Listing\Collection\ListingSortingTranslationBasicCollection;

class ListingSortingTranslationSearchResult extends ListingSortingTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
