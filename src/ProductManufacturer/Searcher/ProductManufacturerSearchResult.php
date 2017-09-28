<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Searcher;

use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\Search\SearchResultInterface;

class ProductManufacturerSearchResult extends ProductManufacturerBasicCollection implements SearchResultInterface
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
