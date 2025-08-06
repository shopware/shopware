<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class TransactionTransformer
{
    /**
     * @return array<int, array<string, string|CalculatedPrice|array<array-key, mixed>|null>>
     */
    public static function transformCollection(
        TransactionCollection $transactions,
        string $stateId,
        Context $context
    ): array {
        $output = [];
        foreach ($transactions as $transaction) {
            $output[] = self::transform($transaction, $stateId, $context);
        }

        return $output;
    }

    /**
     * @return array<string, string|CalculatedPrice|array<array-key, mixed>|null>
     */
    public static function transform(
        Transaction $transaction,
        string $stateId,
        Context $context
    ): array {
        $id = self::getId($transaction);

        return array_filter([
            'id' => $id,
            'paymentMethodId' => $transaction->getPaymentMethodId(),
            'amount' => $transaction->getAmount(),
            'stateId' => !$id ? $stateId : null,
            'validationData' => !$id ? $transaction->getValidationStruct()?->jsonSerialize() : null,
        ]);
    }

    private static function getId(Struct $struct): ?string
    {
        return $struct->getExtensionOfType(
            OrderConverter::ORIGINAL_ID,
            IdStruct::class
        )?->getId();
    }
}
