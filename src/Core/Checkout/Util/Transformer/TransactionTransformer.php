<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Util\Transformer;

use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Defaults;

class TransactionTransformer
{
    public function transformCollection(TransactionCollection $transactions): array
    {
        $output = [];
        foreach ($transactions as $transaction) {
            $output[] = self::transform($transaction);
        }

        return $output;
    }

    public function transform(Transaction $transaction): array
    {
        return [
            'paymentMethodId' => $transaction->getPaymentMethodId(),
            'amount' => $transaction->getAmount(),
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
        ];
    }
}
