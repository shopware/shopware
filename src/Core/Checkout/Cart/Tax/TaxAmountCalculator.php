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

namespace Shopware\Checkout\Cart\Tax;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

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

    public function calculate(CalculatedPriceCollection $priceCollection, CustomerContext $context): CalculatedTaxCollection
    {
        if ($this->taxDetector->isNetDelivery($context)) {
            return new CalculatedTaxCollection([]);
        }

        if ($context->getTouchpoint()->getTaxCalculationType() === self::CALCULATION_VERTICAL) {
            return $priceCollection->getCalculatedTaxes();
        }

        $price = $priceCollection->sum();

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        if ($this->taxDetector->useGross($context)) {
            return $this->taxCalculator->calculateGrossTaxes($price->getTotalPrice(), $rules);
        }

        return $this->taxCalculator->calculateNetTaxes($price->getTotalPrice(), $rules);
    }
}
