<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Delivery>
 */
#[Package('checkout')]
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

    public function getDelivery(DeliveryDate $deliveryDate, ShippingLocation $location): ?Delivery
    {
        foreach ($this->getIterator() as $delivery) {
            if ($delivery->getDeliveryDate()->getEarliest()->format('Y-m-d') !== $deliveryDate->getEarliest()->format('Y-m-d')) {
                continue;
            }

            if ($delivery->getDeliveryDate()->getLatest()->format('Y-m-d') !== $deliveryDate->getLatest()->format('Y-m-d')) {
                continue;
            }

            if ($delivery->getLocation() !== $location) {
                continue;
            }

            return $delivery;
        }

        return null;
    }

    public function contains(LineItem $item): bool
    {
        foreach ($this->getIterator() as $delivery) {
            if ($delivery->getPositions()->has($item->getId())) {
                return true;
            }
        }

        return false;
    }

    public function getShippingCosts(): PriceCollection
    {
        return new PriceCollection(
            $this->map(fn (Delivery $delivery) => $delivery->getShippingCosts())
        );
    }

    public function getAddresses(): CustomerAddressCollection
    {
        $addresses = new CustomerAddressCollection();
        foreach ($this->getIterator() as $delivery) {
            $address = $delivery->getLocation()->getAddress();
            if ($address !== null) {
                $addresses->add($address);
            }
        }

        return $addresses;
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Delivery::class;
    }
}
