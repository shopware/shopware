<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Searcher;

use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductVoteAverageSearchResult extends ProductVoteAverageBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
