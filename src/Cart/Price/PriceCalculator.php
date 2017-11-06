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

namespace Shopware\Cart\Price;

use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;

class PriceCalculator
{
    /**
     * @var TaxCalculator
     */
    private $taxCalculator;

    /**
     * @var PriceRounding
     */
    private $priceRounding;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(
        TaxCalculator $taxCalculator,
        PriceRounding $priceRounding,
        TaxDetector $taxDetector
    ) {
        $this->taxCalculator = $taxCalculator;
        $this->priceRounding = $priceRounding;
        $this->taxDetector = $taxDetector;
    }

    public function calculate(
        PriceDefinition $definition,
        ShopContext $context
    ): Price {
        $unitPrice = $this->getUnitPrice($definition, $context);

        $price = $this->priceRounding->round(
            $unitPrice * $definition->getQuantity()
        );

        $taxRules = $definition->getTaxRules();

        switch (true) {
            case $this->taxDetector->useGross($context):
                $calculatedTaxes = $this->taxCalculator->calculateGrossTaxes($price, $definition->getTaxRules());
                break;

            case $this->taxDetector->isNetDelivery($context):
                $taxRules = new TaxRuleCollection([]);
                $calculatedTaxes = new CalculatedTaxCollection([]);
                break;

            default:
                $calculatedTaxes = $this->taxCalculator->calculateNetTaxes($price, $definition->getTaxRules());
                break;
        }

        return new Price($unitPrice, $price, $calculatedTaxes, $taxRules, $definition->getQuantity());
    }

    private function getUnitPrice(PriceDefinition $definition, ShopContext $context): float
    {
        //unit price already calculated?
        if ($definition->isCalculated()) {
            return $definition->getPrice();
        }

        if (!$this->taxDetector->useGross($context)) {
            return $this->priceRounding->round($definition->getPrice());
        }

        $price = $this->taxCalculator->calculateGross(
            $definition->getPrice(),
            $definition->getTaxRules()
        );

        return $this->priceRounding->round($price);
    }
}
