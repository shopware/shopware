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

namespace Shopware\Core\Content\Product\Cart\Struct;

use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\GoodsInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemInterface;
use Shopware\Core\Checkout\Cart\LineItem\NestedInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\Validatable;
use Shopware\Core\Framework\Struct\Struct;

class CalculatedProduct extends Struct implements DeliverableLineItemInterface, GoodsInterface, Validatable, NestedInterface
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
     * @var MediaStruct|null
     */
    protected $cover;

    /**
     * @var DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var DeliveryDate
     */
    protected $outOfStockDeliveryDate;

    /**
     * @var ProductStruct
     */
    protected $product;

    /**
     * @var CalculatedLineItemCollection
     */
    protected $children;

    public function __construct(
        LineItemInterface $lineItem,
        Price $price,
        string $identifier,
        int $quantity,
        DeliveryDate $inStockDeliveryDate,
        DeliveryDate $outOfStockDeliveryDate,
        ProductStruct $product,
        ?CalculatedLineItemCollection $children = null,
        ?Rule $rule = null,
        ?MediaStruct $cover = null
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

        if (!$children) {
            $children = new CalculatedLineItemCollection();
        }

        if (!$cover && $product && $product->getCover()) {
            $this->cover = $product->getCover()->getMedia();
        }
        $this->children = $children;
    }

    public function addChild(CalculatedLineItemInterface $lineItem)
    {
        $this->children->add($lineItem);
    }

    public function getChildren(): CalculatedLineItemCollection
    {
        return $this->children;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function considerChildrenPrices(): bool
    {
        return true;
    }

    public function getProduct(): ProductStruct
    {
        return $this->product;
    }

    public function getStock(): int
    {
        return $this->product->getStock() ?? 0;
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

    public function getCover(): ?MediaStruct
    {
        return $this->cover;
    }

    public function getDescription(): ?string
    {
        return $this->product->getDescription();
    }

    public function getExtension(string $name): ?Struct
    {
        if (!$this->hasExtension($name)) {
            return $this->product->getExtension($name);
        }

        return parent::getExtension($name);
    }
}
