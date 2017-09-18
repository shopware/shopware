<?php declare(strict_types=1);

namespace Shopware\Currency\Searcher;

use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Search\SearchResultInterface;

class CurrencySearchResult extends CurrencyBasicCollection implements SearchResultInterface
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
