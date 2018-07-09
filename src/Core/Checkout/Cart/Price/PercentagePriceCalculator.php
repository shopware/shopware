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

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\DerivedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\CheckoutContext;

class PercentagePriceCalculator
{
    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        PriceRounding $rounding,
        QuantityPriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->rounding = $rounding;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param float                     $percentage 10.00 for 10%, -10.0 for -10%
     * @param PriceCollection $prices
     * @param CheckoutContext           $context
     *
     * @return DerivedPrice
     */
    public function calculate(
        $percentage,
        PriceCollection $prices,
        CheckoutContext $context
    ): DerivedPrice {
        $price = $prices->sum();

        $discount = $this->rounding->round($price->getTotalPrice() / 100 * $percentage);

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        $definition = new QuantityPriceDefinition($discount, $rules, 1, true);

        $calculatedPrice = $this->priceCalculator->calculate($definition, $context);

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
