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

namespace Shopware\CartBridge\View;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\Price\CartPrice;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Framework\Struct\Struct;

class ViewCart extends Struct
{
    /**
     * @var ViewLineItemCollection
     */
    protected $viewLineItems;

    /**
     * @var \Shopware\Cart\Cart\CalculatedCart
     */
    protected $calculatedCart;

    /**
     * @var ViewDeliveryCollection
     */
    protected $deliveries;

    public function __construct(CalculatedCart $calculatedCart)
    {
        $this->calculatedCart = $calculatedCart;

        $this->viewLineItems = new ViewLineItemCollection(
            $calculatedCart->getCalculatedLineItems()->filterInstance(ViewLineItemInterface::class)->getIterator()->getArrayCopy()
        );

        $this->deliveries = new ViewDeliveryCollection();
    }

    public function getPrice(): CartPrice
    {
        return $this->calculatedCart->getPrice();
    }

    public function getViewLineItems(): ViewLineItemCollection
    {
        return $this->viewLineItems;
    }

    public function getCalculatedCart(): CalculatedCart
    {
        return $this->calculatedCart;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->calculatedCart->getErrors();
    }

    public function getDeliveries(): ViewDeliveryCollection
    {
        return $this->deliveries;
    }

    public function getTotalShippingCosts(): Price
    {
        $deliveries = $this->getDeliveries();
        $totalShippingCosts = new Price(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection());

        /** @var ViewDelivery $delivery */
        foreach ($deliveries as $delivery) {
            $totalShippingCosts->add($delivery->getDelivery()->getShippingCosts());
        }

        return $totalShippingCosts;
    }

    public function clearErrors(): ErrorCollection
    {
        return $this->calculatedCart->clearErrors();
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data = array_merge($data, [
            'price' => $this->getPrice(),
            'errors' => $this->getErrors(),
            'shippingCosts' => $this->getDeliveries()->getShippingCosts()->sum()->getTotalPrice(),
            'deliveries' => $this->getDeliveries(),
        ]);

        return $data;
    }
}
