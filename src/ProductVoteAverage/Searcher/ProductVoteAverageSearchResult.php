<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Searcher;

use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductVoteAverageSearchResult extends ProductVoteAverageBasicCollection implements SearchResultInterface
{
    /**
     * @var int
     */
    protected $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
