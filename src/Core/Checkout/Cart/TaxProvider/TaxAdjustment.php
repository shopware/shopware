<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TaxAdjustment
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AmountCalculator $amountCalculator,
        private readonly CashRounding $rounding
    ) {
    }

    public function adjust(Cart $cart, TaxProviderResult $result, SalesChannelContext $context): void
    {
        $lineItems = $cart->getLineItems();
        $deliveries = $cart->getDeliveries();

        $this->applyLineItemTaxes($lineItems, $result->getLineItemTaxes());
        $this->applyDeliveryTaxes($deliveries, $result->getDeliveryTaxes());

        $price = $this->amountCalculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );

        // either take the price from the tax provider result or take the calculated taxes
        $taxes = $price->getCalculatedTaxes()->filter(fn (CalculatedTax $tax) => $tax->getTax() > 0.0);
        $price->setCalculatedTaxes($taxes);

        if ($result->getCartPriceTaxes()) {
            $price = $this->applyCartPriceTaxes($price, $result->getCartPriceTaxes(), $context);
        }

        $cart->setPrice($price);
    }

    private function applyCartPriceTaxes(CartPrice $price, CalculatedTaxCollection $taxes, SalesChannelContext $context): CartPrice
    {
        $netPrice = $price->getNetPrice();
        $grossPrice = $price->getTotalPrice();
        $taxSum = $taxes->getAmount();

        if ($context->getTaxState() === CartPrice::TAX_STATE_NET) {
            $grossPrice = $this->rounding->cashRound(
                $netPrice + $taxSum,
                $context->getTotalRounding()
            );
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $netPrice = $this->rounding->cashRound(
                $grossPrice - $taxSum,
                $context->getTotalRounding()
            );
        }

        return new CartPrice(
            $netPrice,
            $grossPrice,
            $price->getPositionPrice(),
            $taxes,
            $price->getTaxRules(),
            $context->getTaxState(),
            $grossPrice
        );
    }

    /**
     * @param array<string, CalculatedTaxCollection>|null $taxes
     */
    private function applyLineItemTaxes(LineItemCollection $lineItems, ?array $taxes): void
    {
        if (!$taxes) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            if (!$lineItem->getPrice()) {
                throw CartException::missingLineItemPrice($lineItem->getUniqueIdentifier());
            }

            // trickle down for nested line items
            if ($lineItem->hasChildren()) {
                $this->applyLineItemTaxes($lineItem->getChildren(), $taxes);
            }

            // line item has no tax sum provided
            if (!\array_key_exists($lineItem->getUniqueIdentifier(), $taxes)) {
                continue;
            }

            // apply provided taxes
            $tax = $taxes[$lineItem->getUniqueIdentifier()];
            $lineItem->getPrice()->setCalculatedTaxes($tax);
        }
    }

    /**
     * @param array<string, CalculatedTaxCollection>|null $taxes
     */
    private function applyDeliveryTaxes(DeliveryCollection $deliveries, ?array $taxes): void
    {
        if (!$taxes) {
            return;
        }

        foreach ($taxes as $deliveryPositionId => $deliveryTax) {
            foreach ($deliveries as $delivery) {
                $position = $delivery->getPositions()->get($deliveryPositionId);

                if (!$position) {
                    continue;
                }

                $position->getPrice()->setCalculatedTaxes($deliveryTax);
                $delivery->getShippingCosts()->setCalculatedTaxes($deliveryTax);
            }
        }
    }
}
