<?php declare(strict_types=1);

namespace Shopware\Tax\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Tax\Struct\TaxBasicCollection;

class TaxSearchResult extends TaxBasicCollection implements SearchResultInterface
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
