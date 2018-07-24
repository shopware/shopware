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

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Framework\Struct\Struct;

class DeliveryPosition extends Struct
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    /**
     * @var float
     */
    protected $quantity;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    public function __construct(
        string $identifier,
        LineItem $lineItem,
        int $quantity,
        Price $price,
        DeliveryDate $deliveryDate
    ) {
        $this->lineItem = $lineItem;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->identifier = $identifier;
        $this->deliveryDate = $deliveryDate;
    }

    public static function createByLineItemForInStockDate(LineItem $lineItem): self
    {
        return new self(
            $lineItem->getKey(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getDeliveryInformation()->getInStockDeliveryDate()
        );
    }

    public static function createByLineItemForOutOfStockDate(LineItem $lineItem): self
    {
        return new self(
            $lineItem->getKey(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getDeliveryInformation()->getOutOfStockDeliveryDate()
        );
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }
}
