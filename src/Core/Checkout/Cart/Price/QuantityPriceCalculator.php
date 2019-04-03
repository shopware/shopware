<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
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

    public function __construct(
        GrossPriceCalculator $grossPriceCalculator,
        NetPriceCalculator $netPriceCalculator,
        TaxDetector $taxDetector
    ) {
        $this->grossPriceCalculator = $grossPriceCalculator;
        $this->netPriceCalculator = $netPriceCalculator;
        $this->taxDetector = $taxDetector;
    }

    public function calculateCollection(PriceDefinitionCollection $collection, SalesChannelContext $context): PriceCollection
    {
        $prices = $collection->map(
            function (QuantityPriceDefinition $definition) use ($context) {
                return $this->calculate($definition, $context);
            }
        );

        return new PriceCollection($prices);
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
            $price->getQuantity()
        );
    }
}
