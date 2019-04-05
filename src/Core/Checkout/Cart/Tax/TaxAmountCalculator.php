<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TaxAmountCalculator implements TaxAmountCalculatorInterface
{
    public const CALCULATION_HORIZONTAL = 'horizontal';
    public const CALCULATION_VERTICAL = 'vertical';

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        TaxCalculator $taxCalculator,
        TaxDetector $taxDetector
    ) {
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
        $this->taxCalculator = $taxCalculator;
        $this->taxDetector = $taxDetector;
    }

    public function calculate(PriceCollection $priceCollection, SalesChannelContext $context): CalculatedTaxCollection
    {
        if ($this->taxDetector->isNetDelivery($context)) {
            return new CalculatedTaxCollection([]);
        }

        if ($context->getSalesChannel()->getTaxCalculationType() === self::CALCULATION_VERTICAL) {
            return $priceCollection->getCalculatedTaxes();
        }

        $price = $priceCollection->sum();

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        if ($this->taxDetector->useGross($context)) {
            return $this->taxCalculator->calculateGrossTaxes($price->getTotalPrice(), $context->getContext()->getCurrencyPrecision(), $rules);
        }

        return $this->taxCalculator->calculateNetTaxes($price->getTotalPrice(), $context->getContext()->getCurrencyPrecision(), $rules);
    }
}
