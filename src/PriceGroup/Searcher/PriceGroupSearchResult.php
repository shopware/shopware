<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Searcher;

use Shopware\PriceGroup\Struct\PriceGroupBasicCollection;
use Shopware\Search\SearchResultInterface;

class PriceGroupSearchResult extends PriceGroupBasicCollection implements SearchResultInterface
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
