<?php declare(strict_types=1);

namespace Shopware\Area\Searcher;

use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Search\SearchResultInterface;

class AreaSearchResult extends AreaBasicCollection implements SearchResultInterface
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
