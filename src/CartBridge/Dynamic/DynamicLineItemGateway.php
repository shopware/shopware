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

namespace Shopware\CartBridge\Dynamic;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\DynamicLineItemGatewayInterface;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Context\Struct\ShopContext;

class DynamicLineItemGateway implements DynamicLineItemGatewayInterface
{
    /**
     * @var CustomerGroupDiscountGateway
     */
    private $customerGroupDiscountGateway;

    /**
     * @var MinimumOrderValueGateway
     */
    private $minimumOrderValueGateway;

    /**
     * @var PaymentSurchargeGateway
     */
    private $paymentSurchargeGateway;

    public function __construct(
        CustomerGroupDiscountGateway $customerGroupDiscountGateway,
        MinimumOrderValueGateway $minimumOrderValueGateway,
        PaymentSurchargeGateway $paymentSurchargeGateway
    ) {
        $this->customerGroupDiscountGateway = $customerGroupDiscountGateway;
        $this->minimumOrderValueGateway = $minimumOrderValueGateway;
        $this->paymentSurchargeGateway = $paymentSurchargeGateway;
    }

    public function get(CalculatedCart $cart, ShopContext $context): CalculatedLineItemCollection
    {
        $lineItems = new CalculatedLineItemCollection();

        if ($cart->getCalculatedLineItems()->count() <= 0) {
            return $lineItems;
        }

        if ($lineItem = $this->customerGroupDiscountGateway->get($cart, $context)) {
            $lineItems->add($lineItem);
        }

        if ($lineItem = $this->minimumOrderValueGateway->get($cart, $context)) {
            $lineItems->add($lineItem);
        }

        if ($lineItem = $this->paymentSurchargeGateway->get($cart, $context)) {
            $lineItems->add($lineItem);
        }

        //dispatch surcharge

        return $lineItems;
    }
}
