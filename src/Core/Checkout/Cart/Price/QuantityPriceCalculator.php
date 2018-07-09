<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;

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

    public function calculateCollection(PriceDefinitionCollection $collection, CheckoutContext $context): PriceCollection
    {
        $prices = $collection->map(
            function (QuantityPriceDefinition $definition) use ($context) {
                return $this->calculate($definition, $context);
            }
        );

        return new PriceCollection($prices);
    }

    public function calculate(QuantityPriceDefinition $definition, CheckoutContext $context): Price
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

        return new Price(
            $price->getUnitPrice(),
            $price->getTotalPrice(),
            $calculatedTaxes,
            $taxRules,
            $price->getQuantity()
        );
    }
}
