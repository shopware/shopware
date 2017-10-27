<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;

class ProductVoteAverageSearchResult extends ProductVoteAverageBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
