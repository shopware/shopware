<?php declare(strict_types=1);

namespace Shopware\ProductVote\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;

class ProductVoteSearchResult extends ProductVoteBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
