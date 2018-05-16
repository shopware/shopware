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

namespace Shopware\Checkout\Cart\Cart;

use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Checkout\Cart\Price\AmountCalculator;
use Shopware\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class CartProcessor
{
    /**
     * @var CartProcessorInterface[]
     */
    private $processors;

    /**
     * @var AmountCalculator
     */
    private $calculator;

    public function __construct(iterable $processors, AmountCalculator $calculator)
    {
        $this->processors = $processors;
        $this->calculator = $calculator;
    }

    public function process(Cart $cart, StorefrontContext $context, StructCollection $dataCollection): CalculatedCart
    {
        $lineItems = new CalculatedLineItemCollection(
            $cart->getLineItems()->filterInstance(CalculatedLineItemInterface::class)->getElements()
        );

        $calculatedCart = $this->createCalculatedCart(
            $lineItems,
            new DeliveryCollection(),
            $cart,
            new TransactionCollection(),
            $context
        );

        foreach ($this->processors as $processor) {
            $processor->process($cart, $calculatedCart, $dataCollection, $context);

            $calculatedCart = $this->createCalculatedCart(
                $calculatedCart->getCalculatedLineItems(),
                $calculatedCart->getDeliveries(),
                $cart,
                $calculatedCart->getTransactions(),
                $context
            );
        }

        return $calculatedCart;
    }

    private function createCalculatedCart(
        CalculatedLineItemCollection $lineItems,
        DeliveryCollection $deliveries,
        Cart $container,
        TransactionCollection $transactions,
        StorefrontContext $context
    ): CalculatedCart {
        $price = $this->calculator->calculateAmount(
            $lineItems->getPrices(),
            $deliveries->getShippingCosts(),
            $context
        );

        return new CalculatedCart($container, $lineItems, $price, $deliveries, $transactions);
    }
}
