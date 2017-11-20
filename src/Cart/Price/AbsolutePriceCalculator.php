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

use Shopware\Cart\Price\Struct\DerivedPrice;
use Shopware\Cart\Price\Struct\PriceCollection;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;

class AbsolutePriceCalculator
{
    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        PriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    /**
     * @param float $price
     * @param PriceCollection $prices
     * @param ShopContext $context
     * @return DerivedPrice
     */
    public function calculate(
        float $price,
        PriceCollection $prices,
        ShopContext $context
    ): DerivedPrice {
        $taxRules = $this->percentageTaxRuleBuilder->buildRules($prices->sum());

        $priceDefinition = new PriceDefinition($price, $taxRules, 1, true);

        $calculatedPrice = $this->priceCalculator->calculate($priceDefinition, $context);

        return new DerivedPrice(
            $calculatedPrice->getUnitPrice(),
            $calculatedPrice->getTotalPrice(),
            $calculatedPrice->getCalculatedTaxes(),
            $calculatedPrice->getTaxRules(),
            $calculatedPrice->getQuantity(),
            $prices
        );
    }
}