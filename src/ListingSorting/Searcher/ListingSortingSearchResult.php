<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Searcher;

use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;
use Shopware\Search\SearchResultInterface;

class ListingSortingSearchResult extends ListingSortingBasicCollection implements SearchResultInterface
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
