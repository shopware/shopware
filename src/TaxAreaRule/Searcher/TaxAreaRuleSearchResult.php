<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;

class TaxAreaRuleSearchResult extends TaxAreaRuleBasicCollection implements SearchResultInterface
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
