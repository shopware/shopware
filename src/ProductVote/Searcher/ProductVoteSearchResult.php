<?php declare(strict_types=1);

namespace Shopware\ProductVote\Searcher;

use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductVoteSearchResult extends ProductVoteBasicCollection implements SearchResultInterface
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
