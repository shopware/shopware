<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Searcher;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductDetailPriceSearchResult extends ProductDetailPriceBasicCollection implements SearchResultInterface
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
