<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Searcher;

use Shopware\Search\SearchResultInterface;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopTemplateSearchResult extends ShopTemplateBasicCollection implements SearchResultInterface
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
