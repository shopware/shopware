<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Listing\Collection\ListingSortingTranslationBasicCollection;

class ListingSortingTranslationSearchResult extends ListingSortingTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
