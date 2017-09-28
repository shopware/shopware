<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Searcher;

use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\Search\SearchResultInterface;

class OrderAddressSearchResult extends OrderAddressBasicCollection implements SearchResultInterface
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
