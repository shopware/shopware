<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Framework\Struct\Collection;

class TransactionCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Transaction::class;
    }
}
