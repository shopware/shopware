<?php declare(strict_types=1);

namespace Shopware\Locale\Searcher;

use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\Search\SearchResultInterface;

class LocaleSearchResult extends LocaleBasicCollection implements SearchResultInterface
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
