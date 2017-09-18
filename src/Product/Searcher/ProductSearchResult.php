<?php declare(strict_types=1);

namespace Shopware\Product\Searcher;

use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductSearchResult extends ProductBasicCollection implements SearchResultInterface
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
