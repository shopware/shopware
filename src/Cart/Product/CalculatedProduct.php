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

namespace Shopware\Cart\Product;

use Shopware\Cart\Delivery\Delivery;
use Shopware\Cart\Delivery\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Cart\LineItem\GoodsInterface;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Rule\Rule;
use Shopware\Cart\Rule\Validatable;
use Shopware\Framework\Struct\Struct;

class CalculatedProduct extends Struct implements DeliverableLineItemInterface, GoodsInterface, Validatable
{
    /**
     * @var LineItemInterface
     */
    protected $lineItem;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var DeliveryInformation
     */
    protected $deliveryInformation;

    /**
     * @var null|Delivery
     */
    protected $delivery;

    /**
     * @var null|\Shopware\Cart\Rule\Rule
     */
    private $rule;

    public function __construct(
        string $identifier,
        int $quantity,
        LineItemInterface $lineItem,
        Price $price,
        DeliveryInformation $deliveryInformation,
        ?Rule $rule
    ) {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->lineItem = $lineItem;
        $this->deliveryInformation = $deliveryInformation;
        $this->rule = $rule;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->deliveryInformation->getStock();
    }

    public function getInStockDeliveryDate(): DeliveryDate
    {
        return $this->deliveryInformation->getInStockDeliveryDate();
    }

    public function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return $this->deliveryInformation->getOutOfStockDeliveryDate();
    }

    public function getWeight(): float
    {
        return $this->deliveryInformation->getWeight();
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLineItem(): LineItemInterface
    {
        return $this->lineItem;
    }

    public function getDelivery(): ? Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): void
    {
        $this->delivery = $delivery;
    }

    /**
     * @return null|\Shopware\Cart\Rule\Rule
     */
    public function getRule(): ? Rule
    {
        return $this->rule;
    }

    public function getDeliveryInformation(): DeliveryInformation
    {
        return $this->deliveryInformation;
    }

    public function getType(): string
    {
        return $this->lineItem->getType();
    }
}
