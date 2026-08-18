<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:remove-getter-setter - Will be removed. Use {@link PaginationCounter} instead.
 */
#[Package('after-sales')]
class Counter
{
    private int $counter = 0;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increment(): void
    {
        ++$this->counter;
    }
}
