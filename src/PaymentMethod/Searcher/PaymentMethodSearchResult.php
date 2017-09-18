<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Searcher;

use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\Search\SearchResultInterface;

class PaymentMethodSearchResult extends PaymentMethodBasicCollection implements SearchResultInterface
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
