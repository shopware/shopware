<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Searcher;

use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\Search\SearchResultInterface;

class CustomerGroupDiscountSearchResult extends CustomerGroupDiscountBasicCollection implements SearchResultInterface
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
