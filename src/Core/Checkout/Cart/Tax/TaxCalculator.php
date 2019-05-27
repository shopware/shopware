<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\PriceRoundingInterface;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class TaxCalculator
{
    /**
     * @var PriceRoundingInterface
     */
    private $rounding;

    /**
     * @var TaxRuleCalculator
     */
    private $calculator;

    public function __construct(
        PriceRoundingInterface $rounding,
        TaxRuleCalculator $calculator
    ) {
        $this->rounding = $rounding;
        $this->calculator = $calculator;
    }

    public function calculateGross(float $netPrice, int $precision, TaxRuleCollection $rules): float
    {
        $taxes = $this->calculateNetTaxes($netPrice, $precision, $rules);
        $gross = $netPrice + $taxes->getAmount();

        return $this->rounding->round($gross, $precision);
    }

    public function calculateGrossTaxes(float $price, int $precision, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRule $rule) use ($price, $precision) {
                    return $this->calculator
                        ->calculateTaxFromGrossPrice($price, $precision, $rule);
                }
            )
        );
    }

    public function calculateNetTaxes(float $price, int $precision, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRule $rule) use ($price, $precision) {
                    return $this->calculator
                        ->calculateTaxFromNetPrice($price, $precision, $rule);
                }
            )
        );
    }
}
