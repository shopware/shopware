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

namespace Shopware\Cart\Cart;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Exception\CircularCartCalculationException;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\Price\AmountCalculator;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class CartCalculator
{
    public const MAX_ITERATION = 10;

    /**
     * @var CartProcessorInterface[]
     */
    private $processors;

    /**
     * @var CollectorInterface[]
     */
    private $collectors;

    /**
     * @var AmountCalculator
     */
    private $calculator;

    public function __construct(
        iterable $processors,
        iterable $collectors,
        AmountCalculator $calculator
    ) {
        $this->processors = $processors;
        $this->collectors = $collectors;
        $this->calculator = $calculator;
    }

    public function calculate(Cart $cart, ShopContext $context): CalculatedCart
    {
        $dataCollection = $this->prepare($cart, $context);

        return $this->process($cart, $context, $dataCollection, 0);
    }

    private function prepare(Cart $cart, ShopContext $context): StructCollection
    {
        $fetchCollection = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->prepare($fetchCollection, $cart, $context);
        }

        $dataCollection = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->fetch($dataCollection, $fetchCollection, $context);
        }

        return $dataCollection;
    }

    private function process(
        Cart $cart,
        ShopContext $context,
        StructCollection $dataCollection,
        int $iteration
    ): CalculatedCart {
        if ($iteration >= self::MAX_ITERATION) {
            throw new CircularCartCalculationException();
        }

        $lineItems = new CalculatedLineItemCollection(
            $cart->getLineItems()->filterInstance(
                CalculatedLineItemInterface::class
            )->getElements()
        );

        $calculatedCart = $this->createCalculatedCart(
            $lineItems,
            new DeliveryCollection(),
            $cart,
            $context
        );

        $recalculate = false;

        foreach ($this->processors as $processor) {
            try {
                $processor->process($cart, $calculatedCart, $dataCollection, $context);
            } catch (RecalculateCartException $e) {
                $recalculate = true;
            }

            $calculatedCart = $this->createCalculatedCart(
                $calculatedCart->getCalculatedLineItems(),
                $calculatedCart->getDeliveries(),
                $cart,
                $context
            );
        }

        if ($recalculate) {
            return $this->process($cart, $context, $dataCollection, $iteration + 1);
        }

        return $calculatedCart;
    }

    private function createCalculatedCart(
        CalculatedLineItemCollection $lineItems,
        DeliveryCollection $deliveries,
        Cart $container,
        ShopContext $context
    ): CalculatedCart {
        return new CalculatedCart(
            $container,
            $lineItems,
            $this->calculator->calculateAmount(
                $lineItems->getPrices(),
                $deliveries->getShippingCosts(),
                $context
            ),
            $deliveries
        );
    }
}
