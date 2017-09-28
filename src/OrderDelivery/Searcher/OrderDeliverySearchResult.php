<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Searcher;

use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\Search\SearchResultInterface;

class OrderDeliverySearchResult extends OrderDeliveryBasicCollection implements SearchResultInterface
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
