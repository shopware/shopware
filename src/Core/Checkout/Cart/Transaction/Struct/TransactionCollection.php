<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void             add(Transaction $entity)
 * @method void             set(string $key, Transaction $entity)
 * @method Transaction[]    getIterator()
 * @method Transaction[]    getElements()
 * @method Transaction|null get(string $key)
 * @method Transaction|null first()
 * @method Transaction|null last()
 */
class TransactionCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Transaction::class;
    }
}
