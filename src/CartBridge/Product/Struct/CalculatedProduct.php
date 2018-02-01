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

namespace Shopware\CartBridge\Product\Struct;

use Shopware\Api\Media\Struct\MediaBasicStruct;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Cart\Delivery\Struct\Delivery;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Cart\LineItem\GoodsInterface;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\Struct\Price;
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
     * @var null|Delivery
     */
    protected $delivery;

    /**
     * @var null|Rule
     */
    protected $rule;

    /**
     * @var MediaBasicStruct
     */
    protected $cover;

    /**
     * @var \Shopware\Cart\Delivery\Struct\DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var \Shopware\Cart\Delivery\Struct\DeliveryDate
     */
    protected $outOfStockDeliveryDate;

    /**
     * @var ProductBasicStruct
     */
    protected $product;

    public function __construct(
        LineItemInterface $lineItem,
        Price $price,
        string $identifier,
        int $quantity,
        ProductBasicStruct $product,
        DeliveryDate $inStockDeliveryDate,
        DeliveryDate $outOfStockDeliveryDate,
        ?MediaBasicStruct $cover = null,
        ?Rule $rule = null
    ) {
        $this->lineItem = $lineItem;
        $this->price = $price;
        $this->identifier = $identifier;
        $this->quantity = $quantity;
        $this->product = $product;
        $this->rule = $rule;
        $this->cover = $cover;
        $this->inStockDeliveryDate = $inStockDeliveryDate;
        $this->outOfStockDeliveryDate = $outOfStockDeliveryDate;
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
        return $this->product->getStock();
    }

    public function getInStockDeliveryDate(): DeliveryDate
    {
        return $this->inStockDeliveryDate;
    }

    public function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return $this->outOfStockDeliveryDate;
    }

    public function getWeight(): float
    {
        return $this->product->getWeight();
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

    public function getRule(): ? Rule
    {
        return $this->rule;
    }

    public function getType(): string
    {
        return $this->lineItem->getType();
    }

    public function getLabel(): string
    {
        return $this->product->getName();
    }

    public function getCover(): ?MediaBasicStruct
    {
        return $this->cover;
    }

    public function setCover(MediaBasicStruct $cover): void
    {
        $this->cover = $cover;
    }

    public function getDescription(): ?string
    {
        return $this->product->getDescription();
    }
}
