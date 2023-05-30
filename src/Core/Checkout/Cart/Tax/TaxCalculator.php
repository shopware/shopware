<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class TaxCalculator
{
    public function calculateGross(float $netPrice, TaxRuleCollection $rules): float
    {
        $taxes = $this->calculateNetTaxes($netPrice, $rules);

        return $netPrice + $taxes->getAmount();
    }

    public function calculateGrossTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        $taxes = [];
        foreach ($rules as $rule) {
            $taxes[] = $this->calculateTaxFromGrossPrice($price, $rule);
        }

        return new CalculatedTaxCollection($taxes);
    }

    public function calculateNetTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        $taxes = [];
        foreach ($rules as $rule) {
            $taxes[] = $this->calculateTaxFromNetPrice($price, $rule);
        }

        return new CalculatedTaxCollection($taxes);
    }

    public function calculateTaxFromNetPrice(float $net, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of net price
        $net = $net / 100 * $rule->getPercentage();

        $calculatedTax = $net * ($rule->getTaxRate() / 100);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $net);
    }

    private function calculateTaxFromGrossPrice(float $gross, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of gross price
        $gross = $gross / 100 * $rule->getPercentage();

        $calculatedTax = $gross / ((100 + $rule->getTaxRate()) / 100) * ($rule->getTaxRate() / 100);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $gross);
    }
}
