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
final class OrderInverter
{
    /**
     * Inverts the order into a cancellation: quantities are negated while the item net price stays
     * positive, which EN16931 requires (BR-27 forbids a negative item net price; the reversal is
     * expressed through the negative quantity instead).
     */
    public static function invert(OrderEntity $order): void
    {
        self::invertLineItemPrices($order->getLineItems());

        foreach ($order->getPrice()->getCalculatedTaxes() as $tax) {
            $tax->setTax($tax->getTax() * -1);
            $tax->setPrice($tax->getPrice() * -1);
        }

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $delivery->setShippingCosts(self::invertCalculatedPrice($delivery->getShippingCosts()));
        }

        $order->setShippingTotal($order->getShippingTotal() * -1);
        $order->setAmountNet($order->getAmountNet() * -1);
        $order->setAmountTotal($order->getAmountTotal() * -1);

        $price = $order->getPrice();
        $order->setPrice(new CartPrice(
            $price->getNetPrice() * -1,
            $price->getTotalPrice() * -1,
            $price->getPositionPrice() * -1,
            $price->getCalculatedTaxes(),
            $price->getTaxRules(),
            $price->getTaxStatus(),
            $price->getRawTotal() * -1,
        ));
    }

    private static function invertLineItemPrices(?OrderLineItemCollection $lineItems): void
    {
        if ($lineItems === null) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            $lineItem->setQuantity($lineItem->getQuantity() * -1);
            $lineItem->setTotalPrice($lineItem->getTotalPrice() * -1);

            $price = $lineItem->getPrice();

            if ($price !== null) {
                $lineItem->setPrice(self::invertCalculatedPrice($price));
            }

            self::invertLineItemPrices($lineItem->getChildren());
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
            $price->getUnitPrice(),
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
