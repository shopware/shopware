<?php declare(strict_types=1);

namespace Shopware\Holiday\Searcher;

use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Search\SearchResultInterface;

class HolidaySearchResult extends HolidayBasicCollection implements SearchResultInterface
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
