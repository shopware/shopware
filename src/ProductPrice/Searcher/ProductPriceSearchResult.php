<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Searcher;

use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductPriceSearchResult extends ProductPriceBasicCollection implements SearchResultInterface
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
