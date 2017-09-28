<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Searcher;

use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductDetailSearchResult extends ProductDetailBasicCollection implements SearchResultInterface
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
