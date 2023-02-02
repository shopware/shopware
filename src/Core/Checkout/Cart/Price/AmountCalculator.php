<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class AmountCalculator
{
    private CashRounding $rounding;

    private PercentageTaxRuleBuilder $taxRuleBuilder;

    private TaxCalculator $taxCalculator;

    /**
     * @internal
     */
    public function __construct(
        CashRounding $rounding,
        PercentageTaxRuleBuilder $taxRuleBuilder,
        TaxCalculator $taxCalculator
    ) {
        $this->rounding = $rounding;
        $this->taxRuleBuilder = $taxRuleBuilder;
        $this->taxCalculator = $taxCalculator;
    }

    public function calculate(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            return $this->calculateNetDeliveryAmount($prices, $shippingCosts);
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $this->calculateGrossAmount($prices, $shippingCosts, $context);
        }

        return $this->calculateNetAmount($prices, $shippingCosts, $context);
    }

    /**
     * Calculates the amount for a new delivery.
     * `CalculatedPrice::price` and `CalculatedPrice::netPrice` are equals and taxes are empty.
     */
    private function calculateNetDeliveryAmount(PriceCollection $prices, PriceCollection $shippingCosts): CartPrice
    {
        $positionPrice = $prices->sum();

        $total = $positionPrice->getTotalPrice() + $shippingCosts->sum()->getTotalPrice();

        return new CartPrice(
            $total,
            $total,
            $positionPrice->getTotalPrice(),
            new CalculatedTaxCollection([]),
            new TaxRuleCollection([]),
            CartPrice::TAX_STATE_FREE
        );
    }

    /**
     * Calculates the amount for a gross delivery.
     * `CalculatedPrice::netPrice` contains the summed gross prices minus amount of calculated taxes.
     * `CalculatedPrice::price` contains the summed gross prices
     * Calculated taxes are based on the gross prices
     */
    private function calculateGrossAmount(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);

        $total = $all->sum();

        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            $taxes = new CalculatedTaxCollection([]);
        } else {
            $taxes = $this->calculateTaxes($all, $context);
        }

        $price = $this->rounding->cashRound(
            $total->getTotalPrice(),
            $context->getTotalRounding()
        );

        $net = $this->rounding->mathRound(
            $total->getTotalPrice() - $taxes->getAmount(),
            $context->getItemRounding()
        );

        return new CartPrice(
            $net,
            $price,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_GROSS,
            $total->getTotalPrice()
        );
    }

    /**
     * Calculates the amount for a net based delivery, but gross prices has be be payed
     * `CalculatedPrice::netPrice` contains the summed net prices.
     * `CalculatedPrice::price` contains the summed net prices plus amount of calculated taxes
     * Calculated taxes are based on the net prices
     */
    private function calculateNetAmount(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);

        $total = $all->sum();

        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            $taxes = new CalculatedTaxCollection([]);
        } else {
            $taxes = $this->calculateTaxes($all, $context);
        }

        $price = $this->rounding->cashRound(
            $total->getTotalPrice() + $taxes->getAmount(),
            $context->getTotalRounding()
        );

        return new CartPrice(
            $total->getTotalPrice(),
            $price,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_NET,
            $total->getTotalPrice() + $taxes->getAmount()
        );
    }

    private function calculateTaxes(PriceCollection $prices, SalesChannelContext $context): CalculatedTaxCollection
    {
        if ($context->getTaxCalculationType() === SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL) {
            $taxes = $prices->getCalculatedTaxes();

            $taxes->round(
                $this->rounding,
                $context->getItemRounding()
            );

            return $taxes;
        }

        $price = $prices->sum();

        $rules = $this->taxRuleBuilder->buildRules($price);

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $taxes = $this->taxCalculator->calculateGrossTaxes($price->getTotalPrice(), $rules);
        } else {
            $taxes = $this->taxCalculator->calculateNetTaxes($price->getTotalPrice(), $rules);
        }

        $taxes->round($this->rounding, $context->getItemRounding());

        return $taxes;
    }
}
