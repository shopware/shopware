<?php declare(strict_types=1);
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

namespace Shopware\CartBridge\Dynamic;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;

class MinimumOrderValueGateway
{
    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    /**
     * @param PriceCalculator          $priceCalculator
     * @param PercentageTaxRuleBuilder $percentageTaxRuleBuilder
     */
    public function __construct(PriceCalculator $priceCalculator, PercentageTaxRuleBuilder $percentageTaxRuleBuilder)
    {
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function get(CalculatedCart $cart, ShopContext $context): ? CalculatedLineItemInterface
    {
        if (!$context->getCustomer()) {
            return null;
        }

        $customerGroup = $context->getCurrentCustomerGroup();

        if (!$customerGroup->getMinimumOrderAmount()) {
            return null;
        }

        $goods = $cart->getCalculatedLineItems()->filterGoods();

        if ($goods->count() === 0) {
            return null;
        }

        $price = $goods->getPrices()->sum();

        if ($customerGroup->getMinimumOrderAmount() <= $price->getTotalPrice()) {
            return null;
        }

        $rules = $this->percentageTaxRuleBuilder->buildRules($price);

        $surcharge = $this->priceCalculator->calculate(
            new PriceDefinition($customerGroup->getMinimumOrderAmountSurcharge(), $rules, 1, true),
            $context
        );

        return new CalculatedLineItem(
            'minimum-order-value',
            $surcharge,
            1,
            'surcharge'
        );
    }
}
