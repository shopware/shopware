<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Searcher;

use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductListingPriceSearchResult extends ProductListingPriceBasicCollection implements SearchResultInterface
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
