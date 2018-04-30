<?php declare(strict_types=1);

namespace Shopware\Cart\Transaction\Struct;

use Shopware\Framework\Struct\Collection;

class TransactionCollection extends Collection
{
    /**
     * @var Transaction[]
     */
    protected $elements = [];

    public function add(Transaction $transaction): void
    {
        parent::doAdd($transaction);
    }

    public function remove(string $key): void
    {
        parent::doRemoveByKey($key);
    }
}
