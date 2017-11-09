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

namespace Shopware\Cart\Dynamic;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\CalculatedCartGenerator;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class DynamicProcessor implements CartProcessorInterface
{
    /**
     * @var DynamicLineItemGatewayInterface
     */
    private $gateway;

    /**
     * @param DynamicLineItemGatewayInterface $gateway
     */
    public function __construct(DynamicLineItemGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function process(
        CartContainer $cartContainer,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {
        $lineItems = $this->gateway->get($calculatedCart, $context);

        $calculatedCart->getCalculatedLineItems()->fill($lineItems->getElements());
    }
}
