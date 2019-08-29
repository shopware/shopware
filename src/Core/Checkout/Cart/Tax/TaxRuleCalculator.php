<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;

class TaxRuleCalculator
{
    public function calculateTaxFromGrossPrice(float $gross, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of gross price
        $gross = $gross / 100 * $rule->getPercentage();

        $calculatedTax = $gross / ((100 + $rule->getTaxRate()) / 100) * ($rule->getTaxRate() / 100);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $gross);
    }

    public function calculateTaxFromNetPrice(float $net, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of net price
        $net = $net / 100 * $rule->getPercentage();

        $calculatedTax = $net * ($rule->getTaxRate() / 100);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $net);
    }
}
