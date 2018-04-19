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
use Shopware\Cart\Error\ErrorCollection;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Price\CartPrice;
use Shopware\Framework\Struct\Struct;

class CalculatedCart extends Struct
{
    /**
     * @var CartPrice
     */
    protected $price;

    /**
     * @var CartContainer
     */
    protected $cartContainer;

    /**
     * @var CalculatedLineItemCollection
     */
    protected $calculatedLineItems;

    /**
     * @var DeliveryCollection
     */
    protected $deliveries;

    public function __construct(
        CartContainer $cartContainer,
        CalculatedLineItemCollection $calculatedLineItems,
        CartPrice $price,
        DeliveryCollection $deliveries
    ) {
        $this->cartContainer = $cartContainer;
        $this->calculatedLineItems = $calculatedLineItems;
        $this->price = $price;
        $this->deliveries = $deliveries;
    }

    public function getName(): string
    {
        return $this->cartContainer->getName();
    }

    public function getToken(): string
    {
        return $this->cartContainer->getToken();
    }

    public function getPrice(): CartPrice
    {
        return clone $this->price;
    }

    public function getCartContainer(): CartContainer
    {
        return $this->cartContainer;
    }

    public function getCalculatedLineItems(): CalculatedLineItemCollection
    {
        return clone $this->calculatedLineItems;
    }

    public function getDeliveries(): DeliveryCollection
    {
        return clone $this->deliveries;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->cartContainer->getErrors();
    }

    public function clearErrors(): ErrorCollection
    {
        return $this->cartContainer->clearErrors();
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['shippingCosts'] = $this->getDeliveries()->getShippingCosts()->sum()->getTotalPrice();

        return $data;
    }
}
