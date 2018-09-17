<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxAmountCalculatorInterface;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;

class AmountCalculator
{
    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var TaxAmountCalculatorInterface
     */
    private $taxAmountCalculator;

    public function __construct(
        TaxDetector $taxDetector,
        PriceRounding $rounding,
        TaxAmountCalculatorInterface $taxAmountCalculator
    ) {
        $this->taxDetector = $taxDetector;
        $this->rounding = $rounding;
        $this->taxAmountCalculator = $taxAmountCalculator;
    }

    public function calculate(PriceCollection $prices, PriceCollection $shippingCosts, CheckoutContext $context): CartPrice
    {
        if ($this->taxDetector->isNetDelivery($context)) {
            return $this->calculateNetDeliveryAmount($prices, $shippingCosts);
        }
        if ($this->taxDetector->useGross($context)) {
            return $this->calculateGrossAmount($prices, $shippingCosts, $context);
        }

        return $this->calculateNetAmount($prices, $shippingCosts, $context);
    }

    /**
     * Calculates the amount for a new delivery.
     * `Price::price` and `Price::netPrice` are equals and taxes are empty.
     *
     * @param PriceCollection $prices
     * @param PriceCollection $shippingCosts
     *
     * @return CartPrice
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
     * `Price::netPrice` contains the summed gross prices minus amount of calculated taxes.
     * `Price::price` contains the summed gross prices
     * Calculated taxes are based on the gross prices
     *
     * @param PriceCollection $prices
     * @param PriceCollection $shippingCosts
     * @param CheckoutContext $context
     *
     * @return CartPrice
     */
    private function calculateGrossAmount(PriceCollection $prices, PriceCollection $shippingCosts, CheckoutContext $context): CartPrice
    {
        $allPrices = $prices->merge($shippingCosts);

        $total = $allPrices->sum();

        $positionPrice = $prices->sum();

        $taxes = $this->taxAmountCalculator->calculate($allPrices, $context);

        $net = $total->getTotalPrice() - $taxes->getAmount();
        $net = $this->rounding->round($net);

        return new CartPrice(
            $net,
            $total->getTotalPrice(),
            $positionPrice->getTotalPrice(),
            $taxes,
            $positionPrice->getTaxRules(),
            CartPrice::TAX_STATE_GROSS
        );
    }

    /**
     * Calculates the amount for a net based delivery, but gross prices has be be payed
     * `Price::netPrice` contains the summed net prices.
     * `Price::price` contains the summed net prices plus amount of calculated taxes
     * Calculated taxes are based on the net prices
     *
     * @param PriceCollection $prices
     * @param PriceCollection $shippingCosts
     * @param CheckoutContext $context
     *
     * @return CartPrice
     */
    private function calculateNetAmount(PriceCollection $prices, PriceCollection $shippingCosts, CheckoutContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);

        $total = $all->sum();

        $taxes = $this->taxAmountCalculator->calculate($all, $context);

        $gross = $total->getTotalPrice() + $taxes->getAmount();
        $gross = $this->rounding->round($gross);

        return new CartPrice(
            $total->getTotalPrice(),
            $gross,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_NET
        );
    }
}
