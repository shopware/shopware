<?php declare(strict_types=1);

namespace Shopware\Customer\Searcher;

use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Search\SearchResultInterface;

class CustomerSearchResult extends CustomerBasicCollection implements SearchResultInterface
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
