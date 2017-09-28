<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Searcher;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\Search\SearchResultInterface;

class AreaCountryStateSearchResult extends AreaCountryStateBasicCollection implements SearchResultInterface
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
