<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The shipping method the customer selected per delivery, by the delivery's {@see Delivery::getIdentifier()}
 */
#[Package('checkout')]
final class SelectedDeliveryShippingMethods extends Struct
{
    public const KEY = 'delivery-shipping-methods';

    /**
     * @param array<array-key, string> $shippingMethodIds
     */
    public function __construct(protected array $shippingMethodIds = [])
    {
    }

    /**
     * @return array<array-key, string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->shippingMethodIds;
    }

    public function getShippingMethodId(string $identifier): ?string
    {
        return $this->shippingMethodIds[$identifier] ?? null;
    }

    public function setShippingMethodId(string $identifier, string $shippingMethodId): void
    {
        $this->shippingMethodIds[$identifier] = $shippingMethodId;
    }

    public function removeShippingMethodId(string $identifier): void
    {
        unset($this->shippingMethodIds[$identifier]);
    }

    /**
     * Returns a copy in which the selections of the given struct win for the deliveries both have a selection for
     */
    public function merge(self $selection): self
    {
        return new self(array_replace($this->shippingMethodIds, $selection->getShippingMethodIds()));
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_shipping_methods';
    }
}
