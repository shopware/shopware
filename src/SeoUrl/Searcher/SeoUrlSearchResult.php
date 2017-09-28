<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;

class SeoUrlSearchResult extends SeoUrlBasicCollection implements SearchResultInterface
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
