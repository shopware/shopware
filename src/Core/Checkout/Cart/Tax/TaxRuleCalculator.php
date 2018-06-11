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

use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

class TaxRuleCalculator implements TaxRuleCalculatorInterface
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @param PriceRounding $rounding
     */
    public function __construct(PriceRounding $rounding)
    {
        $this->rounding = $rounding;
    }

    public function supports(TaxRuleInterface $rule): bool
    {
        return $rule instanceof TaxRule;
    }

    public function calculateTaxFromGrossPrice(float $gross, TaxRuleInterface $rule): CalculatedTax
    {
        $calculatedTax = $gross / ((100 + $rule->getRate()) / 100) * ($rule->getRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax);

        return new CalculatedTax($calculatedTax, $rule->getRate(), $gross);
    }

    public function calculateTaxFromNetPrice(float $net, TaxRuleInterface $rule): CalculatedTax
    {
        $calculatedTax = $net * ($rule->getRate() / 100);
        $calculatedTax = $this->rounding->round($calculatedTax);

        return new CalculatedTax($calculatedTax, $rule->getRate(), $net);
    }
}
