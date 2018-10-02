<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

interface TaxRuleCalculatorInterface
{
    public function supports(TaxRuleInterface $rule): bool;

    /**
     * Returns the inclusive taxes of the price
     *
     * Example:   tax rate of 19%
     *            provided price 119.00
     *            returns 19.00 calculated tax
     *
     * @param float            $gross
     * @param TaxRuleInterface $rule
     *
     * @return CalculatedTax
     */
    public function calculateTaxFromGrossPrice(float $gross, TaxRuleInterface $rule): CalculatedTax;

    /**
     * Returns the additional taxes for the price.
     *
     * Example:   tax rate of 19%
     *            provided price 100.00
     *            returns 19.00 calculated tax
     *
     * @param float            $net
     * @param TaxRuleInterface $rule
     *
     * @return CalculatedTax
     */
    public function calculateTaxFromNetPrice(float $net, TaxRuleInterface $rule): CalculatedTax;
}
