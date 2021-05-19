<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryBuilder
{
    public function build(Cart $cart, CartDataCollection $data, SalesChannelContext $context, CartBehavior $cartBehavior): DeliveryCollection
    {
        $key = DeliveryProcessor::buildKey($context->getShippingMethod()->getId());

        if (!$data->has($key)) {
            throw new ShippingMethodNotFoundException($context->getShippingMethod()->getId());
        }

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $data->get($key);

        $delivery = $this->buildSingleDelivery($shippingMethod, $cart->getLineItems(), $context);

        if (!$delivery) {
            return new DeliveryCollection();
        }

        return new DeliveryCollection([$delivery]);
    }

    private function buildSingleDelivery(
        ShippingMethodEntity $shippingMethod,
        LineItemCollection $collection,
        SalesChannelContext $context
    ): ?Delivery {
        $positions = new DeliveryPositionCollection();
        $deliveryTime = null;
        // use shipping method delivery time as default
        if ($shippingMethod->getDeliveryTime() !== null) {
            $deliveryTime = DeliveryTime::createFromEntity($shippingMethod->getDeliveryTime());
        }

        foreach ($collection as $item) {
            if ($item->getDeliveryInformation() === null) {
                continue;
            }

            // each line item can override the delivery time
            if ($item->getDeliveryInformation()->getDeliveryTime()) {
                $deliveryTime = $item->getDeliveryInformation()->getDeliveryTime();
            }

            if ($deliveryTime === null) {
                continue;
            }

            // create the estimated delivery date by detected delivery time
            $deliveryDate = DeliveryDate::createFromDeliveryTime($deliveryTime);

            // create a restock date based on the detected delivery time
            $restockDate = DeliveryDate::createFromDeliveryTime($deliveryTime);

            $restockTime = $item->getDeliveryInformation()->getRestockTime();

            // if the line item has a restock time, add this days to the restock date
            if ($restockTime) {
                $restockDate = $restockDate->add(new \DateInterval('P' . $restockTime . 'D'));
            }

            if ($item->getPrice() === null) {
                continue;
            }

            // if the item is completely instock, use the delivery date
            if ($item->getDeliveryInformation()->getStock() >= $item->getQuantity()) {
                $position = new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), $item->getPrice(), $deliveryDate);
            } else {
                // otherwise use the restock date as delivery date
                $position = new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), $item->getPrice(), $restockDate);
            }

            $positions->add($position);
        }

        if ($positions->count() <= 0) {
            return null;
        }

        return new Delivery(
            $positions,
            $this->getDeliveryDateByPositions($positions),
            $shippingMethod,
            $context->getShippingLocation(),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }

    private function getDeliveryDateByPositions(DeliveryPositionCollection $positions): DeliveryDate
    {
        // this function is only called if the provided collection contains a deliverable line item
        $max = $positions->first()->getDeliveryDate();

        foreach ($positions as $position) {
            $date = $position->getDeliveryDate();

            // detect the latest delivery date
            $earliest = $max->getEarliest() > $date->getEarliest() ? $max->getEarliest() : $date->getEarliest();

            $latest = $max->getLatest() > $date->getLatest() ? $max->getLatest() : $date->getLatest();

            // if earliest and latest is same date, add one day buffer
            if ($earliest->format('Y-m-d') === $latest->format('Y-m-d')) {
                $latest = $latest->add(new \DateInterval('P1D'));
            }

            $max = new DeliveryDate($earliest, $latest);
        }

        return $max;
    }
}
