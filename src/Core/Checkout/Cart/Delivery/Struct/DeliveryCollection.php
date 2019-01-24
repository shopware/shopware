<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Struct\Collection;

class DeliveryCollection extends Collection
{
    /**
     * Sorts the delivery collection by earliest delivery date
     */
    public function sortDeliveries(): self
    {
        $this->sort(function (Delivery $a, Delivery $b) {
            if ($a->getLocation() !== $b->getLocation()) {
                return -1;
            }

            return $a->getDeliveryDate()->getEarliest() > $b->getDeliveryDate()->getEarliest();
        });

        return $this;
    }

    public function getDelivery(DeliveryDate $deliveryDate, ShippingLocation $location): ? Delivery
    {
        /** @var Delivery $delivery */
        foreach ($this->elements as $delivery) {
            if ($delivery->getDeliveryDate()->getEarliest() != $deliveryDate->getEarliest()) {
                continue;
            }
            if ($delivery->getDeliveryDate()->getLatest() != $deliveryDate->getLatest()) {
                continue;
            }

            if ($delivery->getLocation() !== $location) {
                continue;
            }

            return $delivery;
        }

        return null;
    }

    /**
     * @param LineItem $item
     *
     * @return bool
     */
    public function contains(LineItem $item): bool
    {
        /** @var Delivery $delivery */
        foreach ($this->elements as $delivery) {
            if ($delivery->getPositions()->has($item->getKey())) {
                return true;
            }
        }

        return false;
    }

    public function getShippingCosts(): PriceCollection
    {
        return new PriceCollection(
            $this->map(function (Delivery $delivery) {
                return $delivery->getShippingCosts();
            })
        );
    }

    public function getAddresses(): CustomerAddressCollection
    {
        $addresses = new CustomerAddressCollection();
        /** @var Delivery $delivery */
        foreach ($this->elements as $delivery) {
            $address = $delivery->getLocation()->getAddress();
            if ($address !== null) {
                $addresses->add($delivery->getLocation()->getAddress());
            }
        }

        return $addresses;
    }

    protected function getExpectedClass(): ?string
    {
        return Delivery::class;
    }
}
