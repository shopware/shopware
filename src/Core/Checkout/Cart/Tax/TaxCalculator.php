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

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Exception\TaxRuleNotSupportedException;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

class TaxCalculator
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var TaxRuleCalculatorInterface[]
     */
    private $calculators;

    /**
     * @param PriceRounding                         $rounding
     * @param iterable|TaxRuleCalculatorInterface[] $calculators
     */
    public function __construct(
        PriceRounding $rounding,
        iterable $calculators
    ) {
        $this->rounding = $rounding;
        $this->calculators = $calculators;
    }

    public function calculateGross(float $netPrice, TaxRuleCollection $rules): float
    {
        $taxes = $this->calculateNetTaxes($netPrice, $rules);
        $gross = $netPrice + $taxes->getAmount();

        return $this->rounding->round($gross);
    }

    public function calculateGrossTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRuleInterface $rule) use ($price) {
                    return $this->getTaxRuleCalculator($rule)
                        ->calculateTaxFromGrossPrice($price, $rule);
                }
            )
        );
    }

    public function calculateNetTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        return new CalculatedTaxCollection(
            $rules->map(
                function (TaxRuleInterface $rule) use ($price) {
                    return $this->getTaxRuleCalculator($rule)
                        ->calculateTaxFromNetPrice($price, $rule);
                }
            )
        );
    }

    private function getTaxRuleCalculator(TaxRuleInterface $rule): TaxRuleCalculatorInterface
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($rule)) {
                return $calculator;
            }
        }
        throw new TaxRuleNotSupportedException($rule);
    }
}
