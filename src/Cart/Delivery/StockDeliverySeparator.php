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

namespace Shopware\Cart\Delivery;

use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\DeliverableLineItemInterface;
use Shopware\Cart\Price\Price;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\ShippingMethod\Struct\ShippingMethod;

class StockDeliverySeparator
{
    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(PriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    public function addItemsToDeliveries(
        DeliveryCollection $deliveries,
        CalculatedLineItemCollection $items,
        ShopContext $context
    ): DeliveryCollection {
        foreach ($items as $item) {
            if (!$item instanceof DeliverableLineItemInterface) {
                continue;
            }

            if ($deliveries->contains($item)) {
                continue;
            }

            $quantity = $item->getQuantity();

            $position = new DeliveryPosition(
                $item->getIdentifier(),
                clone $item,
                $quantity,
                $item->getPrice(),
                $item->getInStockDeliveryDate()
            );

            //completely in stock?
            if ($item->getStock() >= $quantity) {
                $this->addGoodsToDelivery(
                    $deliveries,
                    $position,
                    $context->getShippingLocation(),
                    $context->getShippingMethod()
                );
                continue;
            }

            //completely out of stock? add full quantity to a delivery with same of out stock delivery date
            if ($item->getStock() <= 0) {
                $position = new DeliveryPosition(
                    $item->getIdentifier(),
                    clone $item,
                    $quantity,
                    $item->getPrice(),
                    $item->getOutOfStockDeliveryDate()
                );

                $this->addGoodsToDelivery(
                    $deliveries,
                    $position,
                    $context->getShippingLocation(),
                    $context->getShippingMethod()
                );
                continue;
            }

            $outOfStock = (int) abs($item->getStock() - $quantity);

            $position = $this->recalculatePosition(
                $item,
                $item->getStock(),
                $item->getInStockDeliveryDate(),
                $context
            );

            $this->addGoodsToDelivery(
                $deliveries,
                $position,
                $context->getShippingLocation(),
                $context->getShippingMethod()
            );

            $position = $this->recalculatePosition(
                $item,
                $outOfStock,
                $item->getOutOfStockDeliveryDate(),
                $context
            );

            $this->addGoodsToDelivery(
                $deliveries,
                $position,
                $context->getShippingLocation(),
                $context->getShippingMethod()
            );
        }

        return clone $deliveries;
    }

    private function recalculatePosition(
        DeliverableLineItemInterface $item,
        int $quantity,
        DeliveryDate $deliveryDate,
        ShopContext $context
    ): DeliveryPosition {
        $definition = new PriceDefinition(
            $item->getPrice()->getUnitPrice(),
            $item->getPrice()->getTaxRules(),
            $quantity,
            true
        );

        $price = $this->priceCalculator->calculate($definition, $context);

        return new DeliveryPosition(
            $item->getIdentifier(),
            clone $item,
            $quantity,
            $price,
            $deliveryDate
        );
    }

    private function addGoodsToDelivery(
        DeliveryCollection $deliveries,
        DeliveryPosition $position,
        ShippingLocation $location,
        ShippingMethod $shippingMethod
    ): void {
        $delivery = $deliveries->getDelivery(
            $position->getDeliveryDate(),
            $location
        );

        if ($delivery) {
            $delivery->getPositions()->add($position);

            return;
        }

        $deliveries->add(
            new Delivery(
                new DeliveryPositionCollection([$position]),
                $position->getDeliveryDate(),
                $shippingMethod,
                $location,
                new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            )
        );
    }
}
