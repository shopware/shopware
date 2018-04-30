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

namespace Shopware\Cart\Delivery\Struct;

use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Framework\Struct\Struct;

class DeliveryPosition extends Struct
{
    /**
     * @var DeliverableLineItemInterface
     */
    protected $calculatedLineItem;

    /**
     * @var float
     */
    protected $quantity;

    /**
     * @var \Shopware\Cart\Price\Struct\CalculatedPrice
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
        DeliverableLineItemInterface $calculatedLineItem,
        int $quantity,
        CalculatedPrice $price,
        DeliveryDate $deliveryDate
    ) {
        $this->calculatedLineItem = $calculatedLineItem;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->identifier = $identifier;
        $this->deliveryDate = $deliveryDate;
    }

    public static function createByLineItemForInStockDate(DeliverableLineItemInterface $lineItem): self
    {
        return new self(
            $lineItem->getIdentifier(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getInStockDeliveryDate()
        );
    }

    public static function createByLineItemForOutOfStockDate(DeliverableLineItemInterface $lineItem): self
    {
        return new self(
            $lineItem->getIdentifier(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getOutOfStockDeliveryDate()
        );
    }

    public function getCalculatedLineItem(): DeliverableLineItemInterface
    {
        return $this->calculatedLineItem;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPrice(): CalculatedPrice
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
