<?php declare(strict_types=1);

namespace Shopware\OrderState\Searcher;

use Shopware\OrderState\Struct\OrderStateBasicCollection;
use Shopware\Search\SearchResultInterface;

class OrderStateSearchResult extends OrderStateBasicCollection implements SearchResultInterface
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
