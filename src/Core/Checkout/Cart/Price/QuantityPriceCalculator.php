<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class QuantityPriceCalculator
{
    /**
     * @var GrossPriceCalculator
     */
    private $grossPriceCalculator;

    /**
     * @var NetPriceCalculator
     */
    private $netPriceCalculator;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var ReferencePriceCalculator
     */
    private $referencePriceCalculator;

    public function __construct(
        GrossPriceCalculator $grossPriceCalculator,
        NetPriceCalculator $netPriceCalculator,
        TaxDetector $taxDetector,
        ReferencePriceCalculator $referencePriceCalculator
    ) {
        $this->grossPriceCalculator = $grossPriceCalculator;
        $this->netPriceCalculator = $netPriceCalculator;
        $this->taxDetector = $taxDetector;
        $this->referencePriceCalculator = $referencePriceCalculator;
    }

    public function calculate(QuantityPriceDefinition $definition, SalesChannelContext $context): CalculatedPrice
    {
        if ($this->taxDetector->useGross($context)) {
            $price = $this->grossPriceCalculator->calculate($definition);
        } else {
            $price = $this->netPriceCalculator->calculate($definition);
        }

        $taxRules = $price->getTaxRules();
        $calculatedTaxes = $price->getCalculatedTaxes();

        if ($this->taxDetector->isNetDelivery($context)) {
            $taxRules = new TaxRuleCollection();
            $calculatedTaxes = new CalculatedTaxCollection();
        }

        return new CalculatedPrice(
            $price->getUnitPrice(),
            $price->getTotalPrice(),
            $calculatedTaxes,
            $taxRules,
            $price->getQuantity(),
            $this->referencePriceCalculator->calculate($price->getUnitPrice(), $definition)
        );
    }
}
