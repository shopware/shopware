<?php
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

namespace Shopware\Cart;

use Shopware\Cart\Cart\CalculatedCartGenerator;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class DynamicProcessor implements CartProcessorInterface
{
    /**
     * @var DynamicLineItemGatewayInterface
     */
    private $gateway;

    /**
     * @var CalculatedCartGenerator
     */
    private $generator;

    /**
     * @param DynamicLineItemGatewayInterface $gateway
     * @param CalculatedCartGenerator         $generator
     */
    public function __construct(DynamicLineItemGatewayInterface $gateway, CalculatedCartGenerator $generator)
    {
        $this->gateway = $gateway;
        $this->generator = $generator;
    }

    public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {
        $calculatedCart = $this->generator->create($cartContainer, $context, $processorCart);

        $lineItems = $this->gateway->get($calculatedCart, $context);

        $processorCart->getCalculatedLineItems()->fill($lineItems->getIterator()->getArrayCopy());
    }
}
