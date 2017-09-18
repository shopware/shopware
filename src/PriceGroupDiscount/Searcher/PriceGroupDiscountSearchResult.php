<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Searcher;

use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\Search\SearchResultInterface;

class PriceGroupDiscountSearchResult extends PriceGroupDiscountBasicCollection implements SearchResultInterface
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
