<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Searcher;

use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductMediaSearchResult extends ProductMediaBasicCollection implements SearchResultInterface
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
