<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Util\Transformer;

use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;

class TransactionTransformer
{
    public static function transformCollection(TransactionCollection $transactions,StateMachineRegistry $stateMachineRegistry,
                                               Context $context): array
    {
        $output = [];
        foreach ($transactions as $transaction) {
            $output[] = self::transform($transaction, $stateMachineRegistry, $context);
        }

        return $output;
    }

    public static function transform(Transaction $transaction,
                                     StateMachineRegistry $stateMachineRegistry,
                                     Context $context): array
    {
        return [
            'paymentMethodId' => $transaction->getPaymentMethodId(),
            'amount' => $transaction->getAmount(),
            'stateId' => $stateMachineRegistry->getInitialState(Defaults::ORDER_TRANSACTION_STATE_MACHINE, $context)->getId(),
        ];
    }
}
