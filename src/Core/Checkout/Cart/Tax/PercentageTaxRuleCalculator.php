<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

class PercentageTaxRuleCalculator implements TaxRuleCalculatorInterface
{
    /**
     * @var TaxRuleCalculatorInterface
     */
    private $taxRuleCalculator;

    public function __construct(TaxRuleCalculatorInterface $taxRuleCalculator)
    {
        $this->taxRuleCalculator = $taxRuleCalculator;
    }

    public function supports(TaxRuleInterface $rule): bool
    {
        return $rule instanceof PercentageTaxRule;
    }

    public function calculateTaxFromGrossPrice(float $gross, TaxRuleInterface $rule): CalculatedTax
    {
        if (!($rule instanceof PercentageTaxRule)) {
            throw new \RuntimeException('Percentual taxes can only be calculated with a percentage tax rule.');
        }

        return $this->taxRuleCalculator->calculateTaxFromGrossPrice(
            $gross / 100 * $rule->getPercentage(),
            new TaxRule($rule->getTaxRate())
        );
    }

    public function calculateTaxFromNetPrice(float $net, TaxRuleInterface $rule): CalculatedTax
    {
        if (!($rule instanceof PercentageTaxRule)) {
            throw new \RuntimeException('Percentual taxes can only be calculated with a percentage tax rule.');
        }

        return $this->taxRuleCalculator->calculateTaxFromNetPrice(
            $net / 100 * $rule->getPercentage(),
            new TaxRule($rule->getTaxRate())
        );
    }
}
