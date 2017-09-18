<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Searcher;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Search\SearchResultInterface;

class AreaCountrySearchResult extends AreaCountryBasicCollection implements SearchResultInterface
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
