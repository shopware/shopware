<?php declare(strict_types=1);

namespace Shopware\Search\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Search\Collection\SearchKeywordBasicCollection;

class SearchKeywordSearchResult extends SearchKeywordBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
