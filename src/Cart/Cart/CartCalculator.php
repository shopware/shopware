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

use Shopware\Cart\Delivery\DeliveryCollection;
use Shopware\Cart\Exception\CircularCartCalculationException;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class CartCalculator
{
    const MAX_ITERATION = 5;

    /**
     * @var CartProcessorInterface[]
     */
    private $processors;

    /**
     * @var CalculatedCartGenerator
     */
    private $calculatedCartGenerator;

    /**
     * @var CollectorInterface[]
     */
    private $collectors;

    /**
     * @var ValidatorInterface[]
     */
    private $validators;

    public function __construct(
        iterable $processors,
        iterable $collectors,
        iterable $validators,
        CalculatedCartGenerator $calculatedCartGenerator
    ) {
        $this->processors = $processors;
        $this->collectors = $collectors;
        $this->calculatedCartGenerator = $calculatedCartGenerator;
        $this->validators = $validators;
    }

    public function calculate(CartContainer $cartContainer, ShopContext $context): CalculatedCart
    {
        $dataCollection = $this->prepare($cartContainer, $context);

        return $this->process($cartContainer, $context, $dataCollection, 0);
    }

    private function prepare(CartContainer $cartContainer, ShopContext $context): StructCollection
    {
        $fetchCollection = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->prepare($fetchCollection, $cartContainer, $context);
        }

        $dataCollection = new StructCollection();
        foreach ($this->collectors as $collector) {
            $collector->fetch($dataCollection, $fetchCollection, $context);
        }

        return $dataCollection;
    }

    private function process(
        CartContainer $cartContainer,
        ShopContext $context,
        StructCollection $dataCollection,
        int $iteration
    ): CalculatedCart {

        if ($iteration >= self::MAX_ITERATION) {
            throw new CircularCartCalculationException();
        }

        $processorCart = new ProcessorCart(
            new CalculatedLineItemCollection(),
            new DeliveryCollection()
        );

        foreach ($this->processors as $processor) {
            $processor->process($cartContainer, $processorCart, $dataCollection, $context);
        }

        $calculatedCart = $this->calculatedCartGenerator->create($cartContainer, $context, $processorCart);

        $recalculate = false;
        foreach ($this->validators as $validator) {
            if ($validator->validate($calculatedCart, $context, $dataCollection)) {
                continue;
            }
            $recalculate = true;
        }

        if ($recalculate) {
            return $this->process($cartContainer, $context, $dataCollection, $iteration + 1);
        }

        return $calculatedCart;
    }
}
