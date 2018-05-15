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
use Shopware\Checkout\Cart\Exception\CircularCartCalculationException;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class CircularCartCalculation
{
    public const MAX_ITERATION = 10;

    /**
     * @var CartProcessor
     */
    private $processor;

    /**
     * @var CartCollector
     */
    private $collector;

    /**
     * @var CartValidator
     */
    private $validator;

    public function __construct(CartProcessor $processor, CartCollector $collector, CartValidator $validator)
    {
        $this->processor = $processor;
        $this->collector = $collector;
        $this->validator = $validator;
    }

    public function calculate(Cart $cart, StorefrontContext $context): CalculatedCart
    {
        $dataCollection = $this->collector->collect($cart, $context);

        return $this->process($cart, $context, $dataCollection, 0);
    }

    private function process(Cart $cart, StorefrontContext $context, StructCollection $dataCollection, int $iteration): CalculatedCart
    {
        if ($iteration >= self::MAX_ITERATION) {
            throw new CircularCartCalculationException();
        }

        $calculatedCart = $this->processor->process($cart, $context, $dataCollection);

        if ($this->validator->isValid($calculatedCart, $context)) {
            return $calculatedCart;
        }

        return $this->process($calculatedCart->getCart(), $context, $dataCollection, $iteration + 1);
    }
}
