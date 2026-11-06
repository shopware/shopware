<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\DatePoint;

#[Package('checkout')]
class DeliveryBuilder
{
    public function build(Cart $cart, CartDataCollection $data, SalesChannelContext $context, CartBehavior $cartBehavior): DeliveryCollection
    {
        $key = DeliveryProcessor::buildKey($context->getShippingMethod()->getId());

        if (!$data->has($key)) {
            throw CartException::shippingMethodNotFound($context->getShippingMethod()->getId());
        }

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $data->get($key);

        return $this->buildByUsingShippingMethod($cart, $shippingMethod, $context);
    }

    public function buildByUsingShippingMethod(Cart $cart, ShippingMethodEntity $shippingMethod, SalesChannelContext $context): DeliveryCollection
    {
        $delivery = $this->buildSingleDelivery($shippingMethod, $cart->getLineItems(), $context);

        if (!$delivery) {
            return new DeliveryCollection();
        }

        return new DeliveryCollection([$delivery]);
    }

    /**
     * Returns the delivery date of the given quantity of the line item:
     * the regular delivery date when the item's stock covers the quantity, the restock date otherwise.
     * Returns null when the item has no delivery information or neither the item nor the default has a delivery time.
     */
    public function resolveDeliveryDate(LineItem $item, int $quantity, ?DeliveryTime $default): ?DeliveryDate
    {
        $deliveryInformation = $item->getDeliveryInformation();
        if ($deliveryInformation === null) {
            return null;
        }

        // each line item can override the delivery time
        $deliveryTime = $default;
        if ($deliveryInformation->getDeliveryTime()) {
            $deliveryTime = $deliveryInformation->getDeliveryTime();
        }

        if ($deliveryTime === null) {
            return null;
        }

        $availableFrom = $this->resolveAvailableFromDate($item);

        // if the item is completely in stock, use the delivery date
        if ($deliveryInformation->getStock() >= $quantity) {
            return DeliveryDate::createFromDeliveryTimeAt($deliveryTime, $availableFrom);
        }

        $restockTime = $deliveryInformation->getRestockTime();
        $restockAvailableFrom = $availableFrom;

        // if the line item has a positive restock time, add this days to the restock date
        if ($restockTime !== null && $restockTime > 0) {
            $restockDateCandidate = Clock::get()->now()->add(new \DateInterval('P' . $restockTime . 'D'));

            if ($restockDateCandidate > $restockAvailableFrom) {
                $restockAvailableFrom = $restockDateCandidate;
            }
        }

        // otherwise use the restock date as delivery date
        return DeliveryDate::createFromDeliveryTimeAt($deliveryTime, $restockAvailableFrom);
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

        $this->buildPositions($collection, $positions, $deliveryTime);

        if (!$positions->first() instanceof DeliveryPosition) {
            return null;
        }

        $maxDeliveryDate = $positions->first()->getDeliveryDate();

        return new Delivery(
            $positions,
            $this->getDeliveryDateByPositions($positions, $maxDeliveryDate),
            $shippingMethod,
            $context->getShippingLocation(),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }

    private function getDeliveryDateByPositions(DeliveryPositionCollection $positions, DeliveryDate $max): DeliveryDate
    {
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

    private function buildPositions(
        LineItemCollection $items,
        DeliveryPositionCollection $positions,
        ?DeliveryTime $default
    ): void {
        foreach ($items as $item) {
            if (!$item->isShippingCostAware()) {
                continue;
            }

            if ($item->getDeliveryInformation() === null) {
                if ($item->getChildren()->count() > 0) {
                    $this->buildPositions($item->getChildren(), $positions, $default);

                    continue;
                }

                if ($default && $item->getPrice()) {
                    $positions->add(new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), clone $item->getPrice(), DeliveryDate::createFromDeliveryTime($default)));
                }

                continue;
            }

            $deliveryDate = $this->resolveDeliveryDate($item, $item->getQuantity(), $default);

            if ($deliveryDate === null || $item->getPrice() === null) {
                continue;
            }

            $positions->add(new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), clone $item->getPrice(), $deliveryDate));
        }
    }

    private function resolveAvailableFromDate(LineItem $item): \DateTimeImmutable
    {
        $releaseDate = $item->getPayloadValue('releaseDate');
        $now = Clock::get()->now();

        if (!\is_string($releaseDate) || trim($releaseDate) === '') {
            return $now;
        }

        // the release date is stored in the payload using the storage date time format
        try {
            $releaseDateTime = DatePoint::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $releaseDate);
        } catch (\Exception) {
            return $now;
        }

        if ($releaseDateTime <= $now) {
            return $now;
        }

        return $releaseDateTime;
    }
}
