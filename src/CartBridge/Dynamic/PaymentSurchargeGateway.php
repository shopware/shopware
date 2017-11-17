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
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;

class PaymentSurchargeGateway
{
    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        PercentagePriceCalculator $percentagePriceCalculator,
        PriceCalculator $priceCalculator
    ) {
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->priceCalculator = $priceCalculator;
    }

    public function get(CalculatedCart $cart, ShopContext $context): ? CalculatedLineItemInterface
    {
        if (!$context->getCustomer()) {
            return null;
        }

        $payment = $context->getPaymentMethod();

        $goods = $cart->getCalculatedLineItems()->filterGoods();

        switch (true) {
            case $payment->getAbsoluteSurcharge() !== null:
                $rules = $this->percentageTaxRuleBuilder->buildRules(
                    $goods->getPrices()->sum()
                );
                $surcharge = $this->priceCalculator->calculate(
                    new PriceDefinition($payment->getAbsoluteSurcharge(), $rules, 1, true),
                    $context
                );

                break;
            case $payment->getPercentageSurcharge() !== null:
                $surcharge = $this->percentagePriceCalculator->calculate(
                    $payment->getPercentageSurcharge(),
                    $goods->getPrices(),
                    $context
                );

                break;
            default:
                return null;
        }

        return new CalculatedLineItem('payment', $surcharge, 1, 'surcharge');
    }
}
