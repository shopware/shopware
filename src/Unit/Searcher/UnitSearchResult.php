<?php declare(strict_types=1);

namespace Shopware\Unit\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\Unit\Struct\UnitBasicCollection;

class UnitSearchResult extends UnitBasicCollection implements SearchResultInterface
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
