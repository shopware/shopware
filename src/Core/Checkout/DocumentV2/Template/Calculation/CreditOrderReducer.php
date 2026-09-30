<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Calculation;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class CreditOrderReducer
{
    /**
     * Reduces the order to the positions a credit note bills. Credit prices are stored negative
     * because they deduct from an invoice total, a credit note states the amount owed back, so they
     * are flipped to positive and the document type carries the credit semantics.
     * Shipping is zeroed, it is never recredited. Deliveries are left intact: they still feed the
     * intra-community-delivery flag and the delivery date the rendered document needs.
     */
    public static function reduce(OrderEntity $order, OrderLineItemCollection $creditItems): void
    {
        self::invertLineItemPrices($creditItems);

        $creditPrice = $creditItems->getPrices()->sum();
        $totalPrice = $creditPrice->getTotalPrice();
        $taxAmount = $creditPrice->getCalculatedTaxes()->getAmount();
        $taxStatus = $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus();

        $hasNetPrices = \in_array(
            $taxStatus,
            [CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE],
            true
        );

        $price = $hasNetPrices
            ? new CartPrice(
                $totalPrice,
                $totalPrice + $taxAmount,
                $totalPrice,
                $creditPrice->getCalculatedTaxes(),
                $creditPrice->getTaxRules(),
                $taxStatus,
            )
            : new CartPrice(
                $totalPrice - $taxAmount,
                $totalPrice,
                $totalPrice,
                $creditPrice->getCalculatedTaxes(),
                $creditPrice->getTaxRules(),
                $taxStatus,
            );

        $order->setLineItems($creditItems);
        $order->setPrice($price);
        $order->setShippingTotal(0.0);
        $order->setPositionPrice($price->getPositionPrice());
        $order->setAmountNet($price->getNetPrice());
        $order->setAmountTotal($price->getTotalPrice());
    }

    private static function invertLineItemPrices(OrderLineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setUnitPrice($lineItem->getUnitPrice() * -1);
            $lineItem->setTotalPrice($lineItem->getTotalPrice() * -1);

            $price = $lineItem->getPrice();

            if ($price !== null) {
                $lineItem->setPrice(self::invertCalculatedPrice($price));
            }

            $children = $lineItem->getChildren();

            if ($children !== null) {
                self::invertLineItemPrices($children);
            }
        }
    }

    private static function invertCalculatedPrice(CalculatedPrice $price): CalculatedPrice
    {
        $calculatedTaxes = $price->getCalculatedTaxes();

        foreach ($calculatedTaxes as $calculatedTax) {
            $calculatedTax->setTax($calculatedTax->getTax() * -1);
            $calculatedTax->setPrice($calculatedTax->getPrice() * -1);
        }

        return new CalculatedPrice(
            $price->getUnitPrice() * -1,
            $price->getTotalPrice() * -1,
            $calculatedTaxes,
            $price->getTaxRules(),
            $price->getQuantity(),
            $price->getReferencePrice(),
            $price->getListPrice(),
            $price->getRegulationPrice(),
        );
    }
}
