<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method DeliveryPosition[]    getIterator()
 * @method DeliveryPosition[]    getElements()
 * @method DeliveryPosition|null first()
 * @method DeliveryPosition|null last()
 */
class DeliveryPositionCollection extends Collection
{
    /**
     * @param DeliveryPosition $deliveryPosition
     */
    public function add($deliveryPosition): void
    {
        $key = $this->getKey($deliveryPosition);
        $this->elements[$key] = $deliveryPosition;
    }

    /**
     * @param string|int       $key
     * @param DeliveryPosition $deliveryPosition
     */
    public function set($key, $deliveryPosition): void
    {
        parent::set($this->getKey($deliveryPosition), $deliveryPosition);
    }

    public function exists(DeliveryPosition $deliveryPosition): bool
    {
        return $this->has($this->getKey($deliveryPosition));
    }

    public function get($identifier): ?DeliveryPosition
    {
        if ($this->has($identifier)) {
            return $this->elements[$identifier];
        }

        return null;
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            array_map(static function (DeliveryPosition $position) {
                return $position->getPrice();
            }, $this->elements)
        );
    }

    public function getLineItems(): LineItemCollection
    {
        return new LineItemCollection(
            array_map(
                function (DeliveryPosition $position) {
                    return $position->getLineItem();
                },
                $this->elements
            )
        );
    }

    public function getWeight(): float
    {
        $weights = $this->getLineItems()->map(function (LineItem $deliverable) {
            if ($deliverable->getDeliveryInformation()) {
                return $deliverable->getDeliveryInformation()->getWeight() * $deliverable->getQuantity();
            }

            return 0;
        });

        return array_sum($weights);
    }

    public function getQuantity(): float
    {
        $quantities = $this->map(function (DeliveryPosition $position) {
            return $position->getQuantity();
        });

        return array_sum($quantities);
    }

    public function getVolume(): float
    {
        $volumes = $this->getLineItems()->map(function (LineItem $deliverable) {
            $information = $deliverable->getDeliveryInformation();
            if ($information === null) {
                return 0;
            }

            $length = $information->getLength();
            $width = $information->getWidth();
            $height = $information->getHeight();

            if ($length === null || $length <= 0.0) {
                return 0;
            }

            if ($width === null || $width <= 0.0) {
                return 0;
            }

            if ($height === null || $height <= 0.0) {
                return 0;
            }

            return ($length * $width * $height) * $deliverable->getQuantity();
        });

        return array_sum($volumes);
    }

    public function getWithoutDeliveryFree(): DeliveryPositionCollection
    {
        return $this->filter(function (DeliveryPosition $position) {
            if ($position->getLineItem()->getDeliveryInformation() !== null && !$position->getLineItem()->getDeliveryInformation()->getFreeDelivery()) {
                return $position;
            }

            return null;
        });
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_position_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DeliveryPosition::class;
    }

    protected function getKey(DeliveryPosition $element): string
    {
        return $element->getIdentifier();
    }
}
