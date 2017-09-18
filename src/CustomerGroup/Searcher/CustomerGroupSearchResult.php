<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Searcher;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Search\SearchResultInterface;

class CustomerGroupSearchResult extends CustomerGroupBasicCollection implements SearchResultInterface
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
