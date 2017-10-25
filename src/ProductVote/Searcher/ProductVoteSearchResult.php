<?php declare(strict_types=1);

namespace Shopware\ProductVote\Searcher;

use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductVoteSearchResult extends ProductVoteBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
