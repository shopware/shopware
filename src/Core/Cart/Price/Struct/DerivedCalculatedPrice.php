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

namespace Shopware\Cart\Price\Struct;

use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;

class DerivedCalculatedPrice extends CalculatedPrice
{
    /**
     * @var CalculatedPriceCollection
     */
    protected $calculationBasePrices;

    public function __construct(
        $unitPrice,
        $totalPrice,
        CalculatedTaxCollection $calculatedTaxes,
        TaxRuleCollection $taxRules,
        $quantity = 1,
        CalculatedPriceCollection $calculationBasePrices
    ) {
        parent::__construct($unitPrice, $totalPrice, $calculatedTaxes, $taxRules, $quantity);

        $this->setCalculationBasePrices($calculationBasePrices);
    }

    public function getCalculationBasePrices(): CalculatedPriceCollection
    {
        return $this->calculationBasePrices;
    }

    /**
     * @param CalculatedPriceCollection $calculationBasePrices
     */
    public function setCalculationBasePrices(CalculatedPriceCollection $calculationBasePrices)
    {
        $this->calculationBasePrices = $calculationBasePrices;
    }
}
