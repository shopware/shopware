<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

class TaxRuleCalculator implements TaxRuleCalculatorInterface
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @param PriceRounding $rounding
     */
    public function __construct(PriceRounding $rounding)
    {
        $this->rounding = $rounding;
    }

    public function supports(TaxRuleInterface $rule): bool
    {
        return $rule instanceof TaxRule;
    }

    public function calculateTaxFromGrossPrice(float $gross, TaxRuleInterface $rule): CalculatedTax
    {
        $calculatedTax = $gross / ((100 + $rule->getTaxRate()) / 100) * ($rule->getTaxRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $gross);
    }

    public function calculateTaxFromNetPrice(float $net, TaxRuleInterface $rule): CalculatedTax
    {
        $calculatedTax = $net * ($rule->getTaxRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax);

        return new CalculatedTax($calculatedTax, $rule->getTaxRate(), $net);
    }
}
