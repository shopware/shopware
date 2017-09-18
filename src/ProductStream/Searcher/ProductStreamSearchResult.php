<?php declare(strict_types=1);

namespace Shopware\ProductStream\Searcher;

use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductStreamSearchResult extends ProductStreamBasicCollection implements SearchResultInterface
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
