<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxAmountCalculatorInterface;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;

class AmountCalculator
{
    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var PriceRounding
     */
    private $rounding;

    /**
     * @var TaxAmountCalculatorInterface
     */
    private $taxAmountCalculator;

    public function __construct(
        TaxDetector $taxDetector,
        PriceRounding $rounding,
        TaxAmountCalculatorInterface $taxAmountCalculator
    ) {
        $this->taxDetector = $taxDetector;
        $this->rounding = $rounding;
        $this->taxAmountCalculator = $taxAmountCalculator;
    }

    public function calculateAmount(CalculatedPriceCollection $prices, CalculatedPriceCollection $shippingCosts, CustomerContext $context): CartPrice
    {
        if ($this->taxDetector->isNetDelivery($context)) {
            return $this->calculateNetDeliveryAmount($prices, $shippingCosts);
        }
        if ($this->taxDetector->useGross($context)) {
            return $this->calculateGrossAmount($prices, $shippingCosts, $context);
        }

        return $this->calculateNetAmount($prices, $shippingCosts, $context);
    }

    /**
     * Calculates the amount for a new delivery.
     * `CalculatedPrice::price` and `CalculatedPrice::netPrice` are equals and taxes are empty.
     *
     * @param CalculatedPriceCollection $prices
     * @param CalculatedPriceCollection $shippingCosts
     *
     * @return CartPrice
     */
    private function calculateNetDeliveryAmount(CalculatedPriceCollection $prices, CalculatedPriceCollection $shippingCosts): CartPrice
    {
        $positionPrice = $prices->sum();

        $total = $positionPrice->getTotalPrice() + $shippingCosts->sum()->getTotalPrice();

        return new CartPrice(
            $total,
            $total,
            $positionPrice->getTotalPrice(),
            new CalculatedTaxCollection([]),
            new TaxRuleCollection([]),
            CartPrice::TAX_STATE_FREE
        );
    }

    /**
     * Calculates the amount for a gross delivery.
     * `CalculatedPrice::netPrice` contains the summed gross prices minus amount of calculated taxes.
     * `CalculatedPrice::price` contains the summed gross prices
     * Calculated taxes are based on the gross prices
     *
     * @param CalculatedPriceCollection                              $prices
     * @param CalculatedPriceCollection                              $shippingCosts
     * @param \Shopware\Core\Checkout\CustomerContext $context
     *
     * @return CartPrice
     */
    private function calculateGrossAmount(CalculatedPriceCollection $prices, CalculatedPriceCollection $shippingCosts, CustomerContext $context): CartPrice
    {
        $allPrices = $prices->merge($shippingCosts);

        $total = $allPrices->sum();

        $positionPrice = $prices->sum();

        $taxes = $this->taxAmountCalculator->calculate($allPrices, $context);

        $net = $total->getTotalPrice() - $taxes->getAmount();
        $net = $this->rounding->round($net);

        return new CartPrice(
            $net,
            $total->getTotalPrice(),
            $positionPrice->getTotalPrice(),
            $taxes,
            $positionPrice->getTaxRules(),
            CartPrice::TAX_STATE_GROSS
        );
    }

    /**
     * Calculates the amount for a net based delivery, but gross prices has be be payed
     * `CalculatedPrice::netPrice` contains the summed net prices.
     * `CalculatedPrice::price` contains the summed net prices plus amount of calculated taxes
     * Calculated taxes are based on the net prices
     *
     * @param CalculatedPriceCollection                              $prices
     * @param CalculatedPriceCollection                              $shippingCosts
     * @param \Shopware\Core\Checkout\CustomerContext $context
     *
     * @return CartPrice
     */
    private function calculateNetAmount(CalculatedPriceCollection $prices, CalculatedPriceCollection $shippingCosts, CustomerContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);

        $total = $all->sum();

        $taxes = $this->taxAmountCalculator->calculate($all, $context);

        $gross = $total->getTotalPrice() + $taxes->getAmount();
        $gross = $this->rounding->round($gross);

        return new CartPrice(
            $total->getTotalPrice(),
            $gross,
            $prices->sum()->getTotalPrice(),
            $taxes,
            $total->getTaxRules(),
            CartPrice::TAX_STATE_NET
        );
    }
}
