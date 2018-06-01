<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Framework\Struct\Collection;

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
