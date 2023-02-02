<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Transaction>
 */
#[Package('checkout')]
class TransactionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_transaction_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Transaction::class;
    }
}
