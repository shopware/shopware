<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Searcher;

use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;
use Shopware\Search\SearchResultInterface;

class OrderLineItemSearchResult extends OrderLineItemBasicCollection implements SearchResultInterface
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
