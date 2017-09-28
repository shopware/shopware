<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Searcher;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Search\SearchResultInterface;

class CustomerAddressSearchResult extends CustomerAddressBasicCollection implements SearchResultInterface
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
