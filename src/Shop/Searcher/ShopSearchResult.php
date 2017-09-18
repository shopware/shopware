<?php declare(strict_types=1);

namespace Shopware\Shop\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Shop\Struct\ShopBasicCollection;

class ShopSearchResult extends ShopBasicCollection implements SearchResultInterface
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
