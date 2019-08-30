<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class TaxCalculator
{
    /**
     * @var TaxRuleCalculator
     */
    private $calculator;

    public function __construct(TaxRuleCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function calculateGross(float $netPrice, TaxRuleCollection $rules): float
    {
        $taxes = $this->calculateNetTaxes($netPrice, $rules);

        return $netPrice + $taxes->getAmount();
    }

    public function calculateGrossTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRule $rule) use ($price) {
                    return $this->calculator
                        ->calculateTaxFromGrossPrice($price, $rule);
                }
            )
        );
    }

    public function calculateNetTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRule $rule) use ($price) {
                    return $this->calculator
                        ->calculateTaxFromNetPrice($price, $rule);
                }
            )
        );
    }
}
