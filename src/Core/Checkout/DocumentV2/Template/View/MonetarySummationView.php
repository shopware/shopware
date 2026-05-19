<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed view of the monetary summation block of an order.
 *
 * `lineTotal` / `chargeTotal` / `allowanceTotal` are summed from the precomputed
 * {@see LineItemView} and {@see AllowanceChargeView} buckets. `grandTotal` / `taxTotal` /
 * `taxBasisTotal` come straight off the order. `prepaid` / `duePayable` are derived from the
 * payment transaction's state.
 *
 * `CalculatedTax` values are already rounded to 2 decimal places, so summing them
 * here does not introduce extra rounding into the rounding amount.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class MonetarySummationView
{
    public function __construct(
        public float $lineTotal,
        public float $chargeTotal,
        public float $allowanceTotal,
        public float $taxBasisTotal,
        public float $taxTotal,
        public string $currencyCode,
        public float $rounding,
        public float $grandTotal,
        public float $prepaid,
        public float $duePayable,
    ) {
    }

    /**
     * @param list<LineItemView> $lineItems
     * @param list<AllowanceChargeView> $allowanceCharges
     */
    public static function fromOrder(OrderEntity $order, array $lineItems, array $allowanceCharges): self
    {
        $currency = $order->getCurrency()?->getIsoCode();

        if ($currency === null) {
            throw DocumentV2Exception::invalidOrderData(
                $order->getId(),
                'currency',
                'Order has no currency assigned.',
            );
        }

        $lineTotal = array_sum(array_map(
            static fn (LineItemView $item): float => $item->lineTotal,
            $lineItems,
        ));

        $chargeTotal = array_sum(array_map(
            static fn (AllowanceChargeView $ac): float => $ac->isCharge ? $ac->actualAmount : 0.0,
            $allowanceCharges,
        ));

        $allowanceTotal = array_sum(array_map(
            static fn (AllowanceChargeView $ac): float => $ac->isCharge ? 0.0 : $ac->actualAmount,
            $allowanceCharges,
        ));

        $grand = $order->getAmountTotal();
        $net = $order->getAmountNet();
        $paid = self::resolvePaidAmount($order);

        return new self(
            lineTotal: $lineTotal,
            chargeTotal: $chargeTotal,
            allowanceTotal: $allowanceTotal,
            taxBasisTotal: $net,
            taxTotal: $grand - $net,
            currencyCode: $currency,
            rounding: $net - $lineTotal - $chargeTotal + $allowanceTotal,
            grandTotal: $grand,
            prepaid: $paid,
            duePayable: $grand - $paid,
        );
    }

    private static function resolvePaidAmount(OrderEntity $order): float
    {
        $transaction = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderTransaction()
            : $order->getTransactions()?->last();

        return $transaction?->getStateMachineState()?->getTechnicalName() === OrderTransactionStates::STATE_PAID
            ? $order->getAmountTotal()
            : 0.0;
    }
}
