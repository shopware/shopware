<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;

class TaxRuleCalculator
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    public function __construct(PriceRounding $rounding)
    {
        $this->rounding = $rounding;
    }

    public function calculateTaxFromGrossPrice(float $gross, int $precision, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of gross price
        $gross = $gross / 100 * $rule->getPercentage();

        $calculatedTax = $gross / ((100 + $rule->getTaxRate()) / 100) * ($rule->getTaxRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax, $precision);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $gross);
    }

    public function calculateTaxFromNetPrice(float $net, int $precision, TaxRule $rule): CalculatedTax
    {
        //calculate percentage value of net price
        $net = $net / 100 * $rule->getPercentage();

        $calculatedTax = $net * ($rule->getTaxRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax, $precision);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $net);
    }
}
