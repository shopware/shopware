<?php declare(strict_types=1);

namespace Shopware\Order\Searcher;

use Shopware\Order\Struct\OrderBasicCollection;
use Shopware\Search\SearchResultInterface;

class OrderSearchResult extends OrderBasicCollection implements SearchResultInterface
{
    /**
     * @var int
     */
    protected $total;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
